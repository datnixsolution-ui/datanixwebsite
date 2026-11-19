/**
 * DATNIX SOLUTIONS - Netlify Serverless Function for Enquiry Dispatch
 * Endpoint: /.netlify/functions/send-enquiry (or /api/send-enquiry via netlify.toml)
 * Runtime: Netlify Functions (AWS Lambda Compatible)
 * 
 * Standardized Fields:
 *  - fullName        (string, required, 2-100 chars, no HTML/CRLF)
 *  - companyName     (string, optional, max 120 chars)
 *  - email           (string, required, RFC-compliant format, max 150 chars)
 *  - phone           (string, optional, 8-15 digits)
 *  - service         (string, required, strictly validated against whitelist)
 *  - estimatedVolume (string, optional, max 100 chars)
 *  - projectDetails  (string, required, 10-5000 chars)
 *  - honeypot        (string, anti-spam trap, must be empty)
 * 
 * Step 3.1 Security Hardening & Rate-Limiting Architecture:
 * 
 * 1. Genuine Sliding-Window Algorithm:
 *    - Sliding Window: 5 requests per 15-minute sliding window (900,000 ms) per client IP.
 *    - Burst Cooldown: Minimum 10 seconds (10,000 ms) between submissions from the same IP.
 *      * Distinguishing note: The 10-second burst limit is an IP-level frequency control to stop
 *        rapid-fire bot automation, regardless of payload content.
 *      * The 30-second duplicate submission check is a content-signature control
 *        (email:service:detailsHash) to prevent accidental double-clicks on identical submissions.
 *    - Atomic Distributed Tier: When Upstash Redis is configured, an atomic Redis Lua script
 *      executes via EVAL on the Redis server, guaranteeing zero race conditions between concurrent requests.
 * 
 * 2. Client IP Resolution (Anti-Spoofing on Netlify):
 *    - Prioritizes x-nf-client-connection-ip (Netlify's edge-enforced, unforgeable connection IP).
 *    - Next evaluates client-ip (Netlify edge router).
 *    - Arbitrary client-sent X-Forwarded-For headers are never blindly trusted.
 * 
 * 3. Storage Boundary Clarification:
 *    - Local /tmp storage (/tmp/datnix_ratelimit_netlify.json) is EPHEMERAL and SCOPED TO AN
 *      INDIVIDUAL AWS LAMBDA / NETLIFY CONTAINER INSTANCE. It survives warm container reloads, but is
 *      NOT globally synchronized across concurrent cold instances or multi-region deployments.
 *    - Only the Redis tier with atomic Lua execution provides global multi-instance synchronization.
 * 
 * 4. Redis Failure & Fallback Resilience:
 *    - If Redis is configured but fails (timeout, network outage, quota exceeded), rate limiting
 *      is NOT silently disabled.
 *    - By default, it safely falls back to local /tmp + memory rate limiting (fail-safe to local).
 *    - If RATE_LIMIT_FAIL_CLOSED=true is set, it strictly rejects with HTTP 503 (fail-closed mode).
 */

import fs from 'fs';
import path from 'path';
import os from 'os';

const TRUSTED_ORIGINS = [
  'https://datnixsolutions.com',
  'https://www.datnixsolutions.com',
  'http://localhost:3000',
  'http://localhost:5000',
  'http://localhost:5500',
  'http://localhost:8000',
  'http://127.0.0.1:5500',
  'http://127.0.0.1:3000',
  'http://127.0.0.1:8080'
];

const ALLOWED_SERVICES = [
  'Data Entry',
  'Data Processing',
  'Back Office Support',
  'Document Management',
  'Web Research',
  'E-commerce Data Management',
  'Data Verification',
  'BPO and Process Support',
  'General Outsourcing',
  'Other'
];

const RATE_LIMIT_WINDOW_MS = 15 * 60 * 1000; // 15 minutes
const RATE_LIMIT_MAX_REQUESTS = 5;          // Max 5 submissions per window
const RATE_LIMIT_BURST_INTERVAL_MS = 10000; // 10 seconds min between requests
const DUPLICATE_WINDOW_MS = 30000;          // 30 seconds duplicate submission window

const memoryRateLimitStore = new Map();
const memoryDuplicateStore = new Map();

function isValidIp(ip) {
  if (!ip || typeof ip !== 'string') return false;
  const clean = ip.trim().replace(/^::ffff:/, '');
  const ipv4Regex = /^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/;
  const ipv6Regex = /^([0-9a-fA-F]{1,4}:){1,7}[0-9a-fA-F]{1,4}$|^::1$|^::$/;
  return ipv4Regex.test(clean) || ipv6Regex.test(clean);
}

// Client IP Resolver for Netlify (Anti-Spoofing)
function resolveClientIp(event) {
  const headers = event.headers || {};
  
  // 1. Netlify Edge cryptographically enforced connection IP (unforgeable by client)
  const nfIp = headers['x-nf-client-connection-ip'] || headers['X-Nf-Client-Connection-Ip'];
  if (nfIp && isValidIp(nfIp.trim())) {
    return nfIp.trim();
  }

  // 2. Netlify Edge client-ip
  const clientIp = headers['client-ip'] || headers['Client-Ip'];
  if (clientIp && isValidIp(clientIp.trim())) {
    return clientIp.trim();
  }

  // 3. Fallback to Cloudflare or proxy header only if valid IP format
  const cfIp = headers['cf-connecting-ip'] || headers['Cf-Connecting-Ip'];
  if (cfIp && isValidIp(cfIp.trim())) {
    return cfIp.trim();
  }

  const forwarded = headers['x-forwarded-for'] || headers['X-Forwarded-For'];
  if (forwarded && typeof forwarded === 'string') {
    const parts = forwarded.split(',').map(p => p.trim()).filter(Boolean);
    if (parts.length > 0 && isValidIp(parts[0])) {
      return parts[0];
    }
  }

  return '127.0.0.1';
}

function getRateLimitFilePath() {
  if (process.env.DATNIX_RATELIMIT_FILE) {
    return process.env.DATNIX_RATELIMIT_FILE;
  }
  return path.join(os.tmpdir(), 'datnix_ratelimit_netlify.json');
}

function readLocalRateLimitStore() {
  const filePath = getRateLimitFilePath();
  try {
    if (fs.existsSync(filePath)) {
      const data = fs.readFileSync(filePath, 'utf8');
      return JSON.parse(data) || {};
    }
  } catch {
    // Fall back to memory
  }
  return {};
}

function writeLocalRateLimitStore(store) {
  const filePath = getRateLimitFilePath();
  try {
    fs.writeFileSync(filePath, JSON.stringify(store), 'utf8');
  } catch {
    // Fall back to memory
  }
}

// Local Container Sliding-Window Evaluator (Fallback / Default)
function evaluateLocalRateLimit(clientIp, now) {
  const fileStore = readLocalRateLimitStore();
  const record = fileStore[clientIp] || memoryRateLimitStore.get(clientIp) || { timestamps: [], lastRequest: 0 };
  const activeTimestamps = (record.timestamps || []).filter(ts => (now - ts) < RATE_LIMIT_WINDOW_MS);

  // Check 1: 10-second burst interval
  if (record.lastRequest && (now - record.lastRequest) < RATE_LIMIT_BURST_INTERVAL_MS) {
    const burstWaitSec = Math.max(1, Math.ceil((RATE_LIMIT_BURST_INTERVAL_MS - (now - record.lastRequest)) / 1000));
    return {
      allowed: false,
      limit: RATE_LIMIT_MAX_REQUESTS,
      remaining: Math.max(0, RATE_LIMIT_MAX_REQUESTS - activeTimestamps.length),
      retryAfter: burstWaitSec,
      reset: Math.floor((now + RATE_LIMIT_WINDOW_MS) / 1000),
      mode: 'local',
      reason: 'Rapid burst detected. Please wait 10 seconds between submissions.'
    };
  }

  // Check 2: 5-request capacity within sliding window
  if (activeTimestamps.length >= RATE_LIMIT_MAX_REQUESTS) {
    const oldest = activeTimestamps[0];
    const retryAfterSec = Math.max(1, Math.ceil((oldest + RATE_LIMIT_WINDOW_MS - now) / 1000));
    return {
      allowed: false,
      limit: RATE_LIMIT_MAX_REQUESTS,
      remaining: 0,
      retryAfter: retryAfterSec,
      reset: Math.floor((oldest + RATE_LIMIT_WINDOW_MS) / 1000),
      mode: 'local',
      reason: 'Too many enquiries from this IP address in the 15-minute window.'
    };
  }

  activeTimestamps.push(now);
  const updatedRecord = { timestamps: activeTimestamps, lastRequest: now };
  fileStore[clientIp] = updatedRecord;
  memoryRateLimitStore.set(clientIp, updatedRecord);

  for (const [ipKey, entry] of Object.entries(fileStore)) {
    const valid = (entry.timestamps || []).filter(t => (now - t) < RATE_LIMIT_WINDOW_MS);
    if (valid.length === 0) {
      delete fileStore[ipKey];
      memoryRateLimitStore.delete(ipKey);
    }
  }
  writeLocalRateLimitStore(fileStore);

  const resetTime = activeTimestamps.length > 0 ? activeTimestamps[0] + RATE_LIMIT_WINDOW_MS : now + RATE_LIMIT_WINDOW_MS;

  return {
    allowed: true,
    limit: RATE_LIMIT_MAX_REQUESTS,
    remaining: RATE_LIMIT_MAX_REQUESTS - activeTimestamps.length,
    retryAfter: 0,
    reset: Math.floor(resetTime / 1000),
    mode: 'local'
  };
}

const REDIS_SLIDING_WINDOW_LUA = `
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
local burst = tonumber(ARGV[4])
local clearBefore = now - window

-- 1. Purge elements outside sliding window
redis.call('ZREMRANGEBYSCORE', key, '-inf', clearBefore)

-- 2. Check 10-second burst interval from latest submission
local latest = redis.call('ZREVRANGEBYSCORE', key, '+inf', '-inf', 'WITHSCORES', 'LIMIT', 0, 1)
if #latest > 0 then
    local lastTime = tonumber(latest[2])
    if (now - lastTime) < burst then
        local burstWait = math.ceil((burst - (now - lastTime)) / 1000)
        return {0, burstWait, 'burst', #latest}
    end
end

-- 3. Check capacity in sliding window
local count = redis.call('ZCARD', key)
if count >= limit then
    local oldest = redis.call('ZRANGEBYSCORE', key, '-inf', '+inf', 'WITHSCORES', 'LIMIT', 0, 1)
    local oldestTime = tonumber(oldest[2])
    local retryAfter = math.max(1, math.ceil((oldestTime + window - now) / 1000))
    return {0, retryAfter, 'window', count}
end

-- 4. Allowed: Record timestamp atomically in Sorted Set
local member = tostring(now) .. ':' .. tostring(math.random(10000, 99999))
redis.call('ZADD', key, now, member)
redis.call('PEXPIRE', key, window)

return {1, 0, 'ok', count + 1}
`;

async function evaluateRateLimit(clientIp) {
  const now = Date.now();
  const upstashUrl = process.env.UPSTASH_REDIS_REST_URL;
  const upstashToken = process.env.UPSTASH_REDIS_REST_TOKEN;
  const failClosed = process.env.RATE_LIMIT_FAIL_CLOSED === 'true';

  if (upstashUrl && upstashToken) {
    try {
      const key = `ratelimit:datnix:${clientIp.replace(/[^a-zA-Z0-9_.-]/g, '_')}`;
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 2000);

      const resp = await fetch(`${upstashUrl}/eval`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${upstashToken}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          script: REDIS_SLIDING_WINDOW_LUA,
          keys: [key],
          args: [
            now.toString(),
            RATE_LIMIT_WINDOW_MS.toString(),
            RATE_LIMIT_MAX_REQUESTS.toString(),
            RATE_LIMIT_BURST_INTERVAL_MS.toString()
          ]
        }),
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      if (resp.ok) {
        const evalData = await resp.json();
        const result = evalData.result;
        if (Array.isArray(result)) {
          const isAllowed = result[0] === 1;
          const retryAfterSec = Number(result[1]) || 0;
          const currentCount = Number(result[3]) || 0;

          return {
            allowed: isAllowed,
            limit: RATE_LIMIT_MAX_REQUESTS,
            remaining: Math.max(0, RATE_LIMIT_MAX_REQUESTS - currentCount),
            retryAfter: retryAfterSec,
            reset: Math.floor((now + RATE_LIMIT_WINDOW_MS) / 1000),
            mode: 'redis',
            reason: result[2]
          };
        }
      }
      throw new Error(`Upstash returned status ${resp.status}`);
    } catch (redisErr) {
      console.warn('[DATNIX NETLIFY RATELIMIT WARNING] Redis failed:', redisErr.message);
      if (failClosed) {
        return {
          allowed: false,
          limit: RATE_LIMIT_MAX_REQUESTS,
          remaining: 0,
          retryAfter: 60,
          reset: Math.floor((now + 60000) / 1000),
          mode: 'fail-closed',
          reason: 'Rate limit verification service unavailable (strict mode).'
        };
      }
    }
  }

  return evaluateLocalRateLimit(clientIp, now);
}

function checkDuplicateSubmission(signature) {
  const now = Date.now();
  if (memoryDuplicateStore.has(signature)) {
    const prevTime = memoryDuplicateStore.get(signature);
    if (now - prevTime < DUPLICATE_WINDOW_MS) {
      const waitSec = Math.max(1, Math.ceil((DUPLICATE_WINDOW_MS - (now - prevTime)) / 1000));
      return { isDuplicate: true, waitSec };
    }
  }
  memoryDuplicateStore.set(signature, now);

  for (const [sig, t] of memoryDuplicateStore.entries()) {
    if (now - t > 60000) memoryDuplicateStore.delete(sig);
  }
  return { isDuplicate: false, waitSec: 0 };
}

function getCorsHeaders(event) {
  const origin = event.headers.origin || event.headers.Origin || '';
  const host = event.headers.host || event.headers.Host || '';

  const headers = {
    'Vary': 'Origin',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Accept, X-Requested-With',
    'Access-Control-Max-Age': '86400',
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'Content-Security-Policy': "default-src 'none'; frame-ancestors 'none'",
    'Cache-Control': 'no-store, no-cache, must-revalidate, private'
  };

  if (!origin) {
    return { headers, originAllowed: true };
  }

  const isTrusted = TRUSTED_ORIGINS.includes(origin) ||
                    (host && origin === `https://${host}`) ||
                    (host && origin === `http://${host}`) ||
                    /^https:\/\/[a-z0-9\-]+\.(netlify\.app|vercel\.app)$/.test(origin);

  if (isTrusted) {
    headers['Access-Control-Allow-Origin'] = origin;
    headers['Access-Control-Allow-Credentials'] = 'true';
    return { headers, originAllowed: true };
  }

  headers['Access-Control-Allow-Origin'] = 'null';
  return { headers, originAllowed: false };
}

export async function handler(event, context) {
  const { headers: corsHeaders, originAllowed } = getCorsHeaders(event);

  // 1. Handle OPTIONS preflight
  if (event.httpMethod === 'OPTIONS') {
    if (!originAllowed) {
      return {
        statusCode: 403,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        body: JSON.stringify({ success: false, error: 'Cross-origin request blocked.' })
      };
    }
    return {
      statusCode: 204,
      headers: corsHeaders,
      body: ''
    };
  }

  // 2. Request Method Restriction
  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      headers: { ...corsHeaders, 'Allow': 'POST, OPTIONS', 'Content-Type': 'application/json' },
      body: JSON.stringify({ success: false, error: 'Method Not Allowed. Only POST requests are accepted.' })
    };
  }

  // Reject untrusted cross-origin requests
  const reqOrigin = event.headers.origin || event.headers.Origin;
  if (reqOrigin && !originAllowed) {
    return {
      statusCode: 403,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({ success: false, error: 'Cross-Origin Request Blocked: Untrusted Origin.' })
    };
  }

  // 3. Request Body Size Limit Check (50KB maximum)
  const bodyString = event.body || '';
  if (bodyString.length > 51200) {
    return {
      statusCode: 413,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({ success: false, error: 'Payload Too Large. Request exceeds the 50KB limit.' })
    };
  }

  // 4. Content-Type Header Verification
  const contentType = (event.headers['content-type'] || event.headers['Content-Type'] || '').toLowerCase();
  if (contentType && !contentType.includes('application/json') && !contentType.includes('application/x-www-form-urlencoded')) {
    return {
      statusCode: 415,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({ success: false, error: 'Unsupported Media Type. Content-Type must be application/json.' })
    };
  }

  // 5. Sliding-Window Rate Limiting Check
  const clientIp = resolveClientIp(event);
  const rateLimitResult = await evaluateRateLimit(clientIp);

  const rateLimitHeaders = {
    ...corsHeaders,
    'Content-Type': 'application/json',
    'X-RateLimit-Limit': rateLimitResult.limit.toString(),
    'X-RateLimit-Remaining': rateLimitResult.remaining.toString(),
    'X-RateLimit-Reset': rateLimitResult.reset.toString(),
    'X-RateLimit-Mode': rateLimitResult.mode || 'local'
  };

  if (!rateLimitResult.allowed) {
    rateLimitHeaders['Retry-After'] = rateLimitResult.retryAfter.toString();
    const statusCode = rateLimitResult.mode === 'fail-closed' ? 503 : 429;
    return {
      statusCode: statusCode,
      headers: rateLimitHeaders,
      body: JSON.stringify({
        success: false,
        error: `Too many submissions from this address. Please wait ${rateLimitResult.retryAfter} seconds before submitting again, or contact us directly on WhatsApp (+91 73870 10393) or email (datnixsolution@gmail.com).`,
        retryAfter: rateLimitResult.retryAfter
      })
    };
  }

  try {
    // 6. Safe JSON Parsing & Structure Verification
    let body = {};
    try {
      body = JSON.parse(bodyString || '{}');
    } catch {
      return {
        statusCode: 400,
        headers: rateLimitHeaders,
        body: JSON.stringify({ success: false, error: 'Invalid JSON request payload.' })
      };
    }

    if (!body || typeof body !== 'object' || Array.isArray(body)) {
      return {
        statusCode: 400,
        headers: rateLimitHeaders,
        body: JSON.stringify({ success: false, error: 'Invalid request payload structure. Expected a JSON object.' })
      };
    }

    // Prototype Pollution Guard
    const forbiddenKeys = ['__proto__', 'constructor', 'prototype'];
    for (const key of Object.keys(body)) {
      if (forbiddenKeys.includes(key.toLowerCase())) {
        return {
          statusCode: 400,
          headers: rateLimitHeaders,
          body: JSON.stringify({ success: false, error: 'Invalid payload key detected.' })
        };
      }
    }

    // Standardized Fields Extraction
    const fullName = String(body.fullName || body['Full Name'] || '').trim();
    const companyName = String(body.companyName || body['Company Name'] || '').trim();
    const email = String(body.email || body.emailAddress || body['Email Address'] || '').trim().toLowerCase();
    const phone = String(body.phone || body.mobileNumber || body['Mobile Number'] || '').trim();
    const serviceInput = String(body.service || body.serviceRequired || body['Service Required'] || '').trim();
    const estimatedVolume = String(body.estimatedVolume || body['Estimated Project Volume'] || '').trim();
    const projectDetails = String(body.projectDetails || body['Project Details'] || '').trim();
    const honeypot = String(body.honeypot || body._gotcha || '').trim();

    // 7. Anti-Spam Honeypot Verification
    if (honeypot !== '') {
      return {
        statusCode: 200,
        headers: rateLimitHeaders,
        body: JSON.stringify({ success: true, message: 'Enquiry received.' })
      };
    }

    // 8. CRLF Injection Guard
    const crlfRegex = /[\r\n]/;
    const singleLineFields = [fullName, companyName, email, phone, serviceInput, estimatedVolume];
    for (const field of singleLineFields) {
      if (crlfRegex.test(field)) {
        return {
          statusCode: 400,
          headers: rateLimitHeaders,
          body: JSON.stringify({ success: false, error: 'Invalid input characters detected in form submission.' })
        };
      }
    }

    // 9. Strict Server-Side Input Validation
    const errors = [];

    if (!fullName || fullName.length < 2 || fullName.length > 100) {
      errors.push('Full Name must be between 2 and 100 characters.');
    } else if (/[<>]/.test(fullName) || !/^[a-zA-Z\s\.\'\-\u00C0-\u024F\u1E00-\u1EFF]+$/.test(fullName)) {
      errors.push('Full Name contains invalid characters.');
    }

    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
    if (!email || !emailRegex.test(email) || email.length > 150) {
      errors.push('A valid business email address is required.');
    }

    if (phone) {
      if (phone.length > 25) {
        errors.push('Phone number is too long.');
      } else if (!/^[0-9+\s\-()]+$/.test(phone)) {
        errors.push('Phone number contains invalid characters.');
      } else {
        const cleanDigits = phone.replace(/[^0-9]/g, '');
        if (cleanDigits.length < 8 || cleanDigits.length > 15) {
          errors.push('Phone number must contain between 8 and 15 digits.');
        }
      }
    }

    if (companyName) {
      if (companyName.length > 120) {
        errors.push('Company Name must not exceed 120 characters.');
      } else if (/[<>]/.test(companyName)) {
        errors.push('Company Name contains invalid characters.');
      }
    }

    let canonicalService = '';
    const matchedService = ALLOWED_SERVICES.find(
      s => s.toLowerCase() === serviceInput.toLowerCase()
    );
    if (!matchedService) {
      errors.push('Invalid service selection. Please choose a valid service from our catalog.');
    } else {
      canonicalService = matchedService;
    }

    if (estimatedVolume) {
      if (estimatedVolume.length > 100) {
        errors.push('Estimated Volume must not exceed 100 characters.');
      } else if (/[<>]/.test(estimatedVolume)) {
        errors.push('Estimated Volume contains invalid characters.');
      }
    }

    if (!projectDetails || projectDetails.length < 10 || projectDetails.length > 5000) {
      errors.push('Project Details must be between 10 and 5,000 characters.');
    }

    if (errors.length > 0) {
      return {
        statusCode: 400,
        headers: rateLimitHeaders,
        body: JSON.stringify({ success: false, error: errors.join(' ') })
      };
    }

    // 10. Content-Signature Duplicate Submission Check (30-second window)
    const submissionSig = `${email}:${canonicalService}:${projectDetails.slice(0, 50).trim()}`;
    const duplicateCheck = checkDuplicateSubmission(submissionSig);
    if (duplicateCheck.isDuplicate) {
      return {
        statusCode: 429,
        headers: { ...rateLimitHeaders, 'Retry-After': duplicateCheck.waitSec.toString() },
        body: JSON.stringify({
          success: false,
          error: `Duplicate enquiry detected. Please wait ${duplicateCheck.waitSec} seconds before resubmitting.`,
          retryAfter: duplicateCheck.waitSec
        })
      };
    }

    // 11. Environment Variables Check
    const resendApiKey = process.env.RESEND_API_KEY;
    const sendgridApiKey = process.env.SENDGRID_API_KEY;
    const recipientEmail = process.env.NOTIFICATION_EMAIL || 'datnixsolution@gmail.com';
    const senderEmail = process.env.SENDER_EMAIL || 'onboarding@resend.dev';

    if (!resendApiKey && !sendgridApiKey) {
      console.warn('[DATNIX NETLIFY NOTICE] Email service not configured.');
      return {
        statusCode: 503,
        headers: rateLimitHeaders,
        body: JSON.stringify({
          success: false,
          error: 'Email delivery backend is currently not configured. Set RESEND_API_KEY in hosting environment variables.'
        })
      };
    }

    // 12. Branded HTML Email Template
    const safeName = escapeHtml(fullName);
    const safeCompany = escapeHtml(companyName || 'Not specified');
    const safeEmail = escapeHtml(email);
    const safePhone = escapeHtml(phone || 'Not provided');
    const safeService = escapeHtml(canonicalService);
    const safeVolume = escapeHtml(estimatedVolume || 'Flexible / Not specified');
    const safeDetails = escapeHtml(projectDetails).replace(/\n/g, '<br>');
    const submissionTime = new Date().toUTCString();

    const emailSubject = `New DATNIX SOLUTIONS Website Enquiry: ${safeService} - ${safeName} (${safeCompany})`;

    const emailHtml = `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>${emailSubject}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F4F6FA; margin: 0; padding: 24px; color: #1E293B; }
    .container { max-width: 620px; margin: 0 auto; background: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 14px rgba(9, 45, 120, 0.08); border: 1px solid #E2E8F0; }
    .header { background: #092D78; border-top: 4px solid #FF7900; padding: 24px; text-align: center; color: #FFFFFF; }
    .header h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.04em; }
    .header p { margin: 6px 0 0; font-size: 12px; color: #E2E8F0; letter-spacing: 0.08em; text-transform: uppercase; }
    .content { padding: 28px 24px; }
    .lead-banner { background: #FFF4EB; border-left: 4px solid #FF7900; padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; font-size: 14px; color: #9A3412; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    th { text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; color: #64748B; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; width: 35%; }
    td { padding: 10px 12px; font-size: 14px; color: #0F172A; border-bottom: 1px solid #F1F5F9; }
    .scope-box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 16px; font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 24px; }
    .action-row { text-align: center; margin: 28px 0 12px; }
    .btn { display: inline-block; background: #FF7900; color: #FFFFFF !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 700; font-size: 14px; }
    .footer { background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 24px; font-size: 11px; color: #94A3B8; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>DATNIX SOLUTIONS</h1>
      <p>DATA. PROCESS. PERFORMANCE.</p>
    </div>
    <div class="content">
      <div class="lead-banner">New Business Enquiry Received via datnixsolutions.com</div>
      <table>
        <tr><th>Client Name</th><td><strong>${safeName}</strong></td></tr>
        <tr><th>Company</th><td>${safeCompany}</td></tr>
        <tr><th>Business Email</th><td><a href="mailto:${safeEmail}">${safeEmail}</a></td></tr>
        <tr><th>Phone Number</th><td>${safePhone}</td></tr>
        <tr><th>Service Required</th><td><span style="color: #092D78; font-weight: 700;">${safeService}</span></td></tr>
        <tr><th>Estimated Volume</th><td>${safeVolume}</td></tr>
        <tr><th>Submission Time</th><td>${submissionTime}</td></tr>
      </table>
      <div style="font-weight: 700; font-size: 13px; text-transform: uppercase; color: #64748B; margin-bottom: 8px;">Project Requirements & Scope:</div>
      <div class="scope-box">${safeDetails}</div>
      <div class="action-row">
        <a href="mailto:${safeEmail}?subject=Re:%20${encodeURIComponent(emailSubject)}" class="btn">Reply to Client</a>
      </div>
    </div>
    <div class="footer">DATNIX SOLUTIONS &bull; Secure Website Lead Notification &bull; +91 73870 10393</div>
  </div>
</body>
</html>`;

    let providerSuccess = false;
    let providerResponseId = '';

    if (resendApiKey) {
      const resendResp = await fetch('https://api.resend.com/emails', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${resendApiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          from: senderEmail,
          to: [recipientEmail],
          reply_to: email,
          subject: emailSubject,
          html: emailHtml
        })
      });

      const resendData = await resendResp.json().catch(() => ({}));
      if (resendResp.ok && resendData.id) {
        providerSuccess = true;
        providerResponseId = resendData.id;
      } else {
        throw new Error('Resend rejected email dispatch.');
      }
    } else if (sendgridApiKey) {
      const sgResp = await fetch('https://api.sendgrid.com/v3/mail/send', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${sendgridApiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          personalizations: [{ to: [{ email: recipientEmail }], subject: emailSubject }],
          from: { email: senderEmail, name: 'DATNIX SOLUTIONS' },
          reply_to: { email: email, name: fullName },
          content: [{ type: 'text/html', value: emailHtml }]
        })
      });

      if (sgResp.status === 200 || sgResp.status === 202) {
        providerSuccess = true;
        providerResponseId = 'sendgrid-ok';
      } else {
        throw new Error('SendGrid rejected email dispatch.');
      }
    }

    if (providerSuccess) {
      return {
        statusCode: 200,
        headers: rateLimitHeaders,
        body: JSON.stringify({
          success: true,
          message: 'Your enquiry has been delivered to datnixsolution@gmail.com. We will contact you within 2-4 business hours.',
          deliveryId: providerResponseId
        })
      };
    } else {
      throw new Error('Email provider did not confirm dispatch.');
    }
  } catch (err) {
    console.error('[DATNIX NETLIFY EXCEPTION]', err.message);
    return {
      statusCode: 502,
      headers: rateLimitHeaders,
      body: JSON.stringify({
        success: false,
        error: 'Enquiry delivery service is temporarily unavailable. Please contact datnixsolution@gmail.com directly.'
      })
    };
  }
}

function escapeHtml(str) {
  return String(str).replace(/[&<>'"]/g, tag => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[tag] || tag));
}
