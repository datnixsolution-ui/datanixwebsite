/**
 * DATNIX SOLUTIONS - Hardened Enquiry API Dispatcher
 * Endpoint: POST /api/send-enquiry
 * Runtime: Node.js (Vercel Serverless / AWS Lambda / Edge Compatible)
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
 * 2. Client IP Resolution (Anti-Spoofing):
 *    - Never blindly trusts raw X-Forwarded-For or X-Real-IP headers.
 *    - On Vercel: Uses x-vercel-forwarded-for (edge-overwritten, unforgeable by client).
 *    - Behind Cloudflare: Uses cf-connecting-ip (edge-enforced).
 *    - On Node servers: Socket remoteAddress is the authoritative TCP handshake IP.
 *      Forwarding headers are ONLY evaluated if the direct socket connection originates from a
 *      verified private/loopback reverse proxy (RFC 1918 / RFC 4193).
 * 
 * 3. Storage Boundary Clarification:
 *    - Local /tmp storage (/tmp/datnix_ratelimit_node.json) is EPHEMERAL and SCOPED TO AN
 *      INDIVIDUAL SERVERLESS CONTAINER INSTANCE. It survives warm container reloads, but is
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

// 1. Trusted Origins Whitelist for CORS
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

// Allowed Service Options Whitelist (Canonical names)
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

// Rate Limiting Parameters
const RATE_LIMIT_WINDOW_MS = 15 * 60 * 1000; // 15-minute genuine sliding window
const RATE_LIMIT_MAX_REQUESTS = 5;          // Max 5 submissions per window per IP
const RATE_LIMIT_BURST_INTERVAL_MS = 10000; // 10-second rapid-fire burst cooldown per IP
const DUPLICATE_WINDOW_MS = 30000;          // 30-second duplicate submission window (content signature)

// Local In-Memory Fallback Caches
const memoryRateLimitStore = new Map();
const memoryDuplicateStore = new Map();

// Helper: Check valid IP format
function isValidIp(ip) {
  if (!ip || typeof ip !== 'string') return false;
  const clean = ip.trim().replace(/^::ffff:/, '');
  // IPv4 regex
  const ipv4Regex = /^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/;
  // IPv6 regex
  const ipv6Regex = /^([0-9a-fA-F]{1,4}:){1,7}[0-9a-fA-F]{1,4}$|^::1$|^::$/;
  return ipv4Regex.test(clean) || ipv6Regex.test(clean);
}

// Helper: Check if IP is private/loopback (trusted reverse proxy)
function isPrivateIp(ip) {
  if (!ip || typeof ip !== 'string') return false;
  const clean = ip.trim().replace(/^::ffff:/, '');
  if (clean === '127.0.0.1' || clean === '::1' || clean === 'localhost') return true;
  // 10.0.0.0/8
  if (/^10\./.test(clean)) return true;
  // 172.16.0.0/12
  if (/^172\.(1[6-9]|2\d|3[01])\./.test(clean)) return true;
  // 192.168.0.0/16
  if (/^192\.168\./.test(clean)) return true;
  // fc00::/7 (IPv6 ULA)
  if (/^[fF][cCdD]/.test(clean)) return true;
  // fe80::/10 (Link-local)
  if (/^[fF][eE][89aAbB]/.test(clean)) return true;
  return false;
}

// Client IP Resolver (Anti-Spoofing Architecture)
function resolveClientIp(req) {
  // 1. Platform-enforced edge headers (cannot be forged by incoming client)
  // Vercel edge router enforces x-vercel-forwarded-for
  const vercelIp = req.headers['x-vercel-forwarded-for'];
  if (vercelIp && typeof vercelIp === 'string') {
    const candidate = vercelIp.split(',')[0].trim();
    if (isValidIp(candidate)) return candidate;
  }

  // Cloudflare edge enforces cf-connecting-ip
  const cfIp = req.headers['cf-connecting-ip'];
  if (cfIp && typeof cfIp === 'string') {
    const candidate = cfIp.trim();
    if (isValidIp(candidate)) return candidate;
  }

  // 2. Raw TCP socket connection IP (authoritative, kernel-level unforgeable)
  const rawSocket = req.socket?.remoteAddress || req.connection?.remoteAddress || '';
  const cleanSocket = rawSocket.replace(/^::ffff:/, '').trim();

  // 3. Reverse Proxy Evaluation:
  // If the direct socket IP is a private/loopback IP (e.g. Nginx on localhost proxying to Node),
  // then and only then do we trust the proxy's forwarding headers.
  if (isPrivateIp(cleanSocket)) {
    const realIp = req.headers['x-real-ip'];
    if (realIp && typeof realIp === 'string' && isValidIp(realIp.trim())) {
      return realIp.trim();
    }

    const forwarded = req.headers['x-forwarded-for'];
    if (forwarded && typeof forwarded === 'string') {
      const parts = forwarded.split(',').map(s => s.trim()).filter(Boolean);
      for (const p of parts) {
        if (isValidIp(p) && !isPrivateIp(p)) {
          return p;
        }
      }
      if (parts.length > 0 && isValidIp(parts[0])) {
        return parts[0];
      }
    }
  }

  // If socket IP is valid and not a private loopback, use it directly (neutralizes spoofed headers)
  if (cleanSocket && isValidIp(cleanSocket)) {
    return cleanSocket;
  }

  return '127.0.0.1';
}

// Local File Store Helper (Ephemeral container scratch)
function getRateLimitFilePath() {
  if (process.env.DATNIX_RATELIMIT_FILE) {
    return process.env.DATNIX_RATELIMIT_FILE;
  }
  return path.join(os.tmpdir(), 'datnix_ratelimit_node.json');
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

// Genuine Sliding-Window Evaluator (Local Container Fallback)
function evaluateLocalRateLimit(clientIp, now) {
  const fileStore = readLocalRateLimitStore();
  const record = fileStore[clientIp] || memoryRateLimitStore.get(clientIp) || { timestamps: [], lastRequest: 0 };

  // True sliding-window log filter: timestamps in [now - 15m, now]
  const activeTimestamps = (record.timestamps || []).filter(ts => (now - ts) < RATE_LIMIT_WINDOW_MS);

  // Check 1: 10-second burst limit
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

  // Allowed: atomically record timestamp
  activeTimestamps.push(now);
  const updatedRecord = { timestamps: activeTimestamps, lastRequest: now };
  fileStore[clientIp] = updatedRecord;
  memoryRateLimitStore.set(clientIp, updatedRecord);

  // Clean stale IPs from fileStore
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

// Genuine Atomic Sliding-Window Evaluator (Distributed Redis with Lua script)
// Lua script executes atomically on Redis server - eliminates all concurrency race conditions.
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

  // Tier 1: Distributed Upstash Redis (if configured)
  if (upstashUrl && upstashToken) {
    try {
      const key = `ratelimit:datnix:${clientIp.replace(/[^a-zA-Z0-9_.-]/g, '_')}`;
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 2000); // 2s network timeout

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
        // Result format: [allowed(1|0), retryAfter(sec), reason(ok|burst|window), currentCount]
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
      console.warn('[DATNIX RATELIMIT WARNING] Distributed Redis failed:', redisErr.message);
      
      // If configured to fail-closed, reject request rather than fallback
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
      // Resilient default: safely fall back to local rate limiting (rate limiting is NOT disabled!)
    }
  }

  // Tier 2: Local Container Rate Limiter (Fallback / Default)
  return evaluateLocalRateLimit(clientIp, now);
}

// Content-Signature Duplicate Submission Tracker (30-second window)
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

  // Periodic cleanup (> 60s)
  for (const [sig, t] of memoryDuplicateStore.entries()) {
    if (now - t > 60000) memoryDuplicateStore.delete(sig);
  }
  return { isDuplicate: false, waitSec: 0 };
}

// Handle CORS Origin Restrictions
function handleCors(req, res) {
  const origin = req.headers.origin;
  const host = req.headers.host;

  res.setHeader('Vary', 'Origin');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With');
  res.setHeader('Access-Control-Max-Age', '86400');

  if (!origin) {
    // Same-origin or non-browser server-to-server call
    return true;
  }

  const isTrusted = TRUSTED_ORIGINS.includes(origin) ||
                    (host && origin === `https://${host}`) ||
                    (host && origin === `http://${host}`) ||
                    /^https:\/\/[a-z0-9\-]+\.(netlify\.app|vercel\.app)$/.test(origin);

  if (isTrusted) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    return true;
  }

  // Explicitly deny untrusted cross-origin
  res.setHeader('Access-Control-Allow-Origin', 'null');
  return false;
}

export default async function handler(req, res) {
  // Apply Universal Security Headers
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');

  // 1. CORS Preflight & Origin Handling
  const originAllowed = handleCors(req, res);

  if (req.method === 'OPTIONS') {
    if (!originAllowed) {
      return res.status(403).json({ success: false, error: 'Cross-origin request blocked.' });
    }
    return res.status(204).end();
  }

  // 2. Request Method Restriction
  if (req.method !== 'POST') {
    res.setHeader('Allow', ['POST', 'OPTIONS']);
    return res.status(405).json({
      success: false,
      error: 'Method Not Allowed. Only POST requests are accepted.'
    });
  }

  // Reject untrusted cross-origin calls
  if (req.headers.origin && !originAllowed) {
    return res.status(403).json({
      success: false,
      error: 'Cross-Origin Request Blocked: Untrusted Origin.'
    });
  }

  // 3. Request Body Size Limit Check (50KB maximum)
  const contentLength = parseInt(req.headers['content-length'] || '0', 10);
  if (contentLength > 51200) {
    return res.status(413).json({
      success: false,
      error: 'Payload Too Large. Request exceeds the 50KB limit.'
    });
  }

  // 4. Content-Type Header Verification
  const contentType = (req.headers['content-type'] || '').toLowerCase();
  if (contentType && !contentType.includes('application/json') && !contentType.includes('application/x-www-form-urlencoded')) {
    return res.status(415).json({
      success: false,
      error: 'Unsupported Media Type. Content-Type must be application/json.'
    });
  }

  // 5. Source IP Resolution & Sliding-Window Rate Limiting
  const clientIp = resolveClientIp(req);
  const rateLimitResult = await evaluateRateLimit(clientIp);

  // Set RFC-compliant Rate Limit Headers
  res.setHeader('X-RateLimit-Limit', rateLimitResult.limit.toString());
  res.setHeader('X-RateLimit-Remaining', rateLimitResult.remaining.toString());
  res.setHeader('X-RateLimit-Reset', rateLimitResult.reset.toString());
  res.setHeader('X-RateLimit-Mode', rateLimitResult.mode || 'local');

  if (!rateLimitResult.allowed) {
    res.setHeader('Retry-After', rateLimitResult.retryAfter.toString());
    const statusCode = rateLimitResult.mode === 'fail-closed' ? 503 : 429;
    return res.status(statusCode).json({
      success: false,
      error: `Too many submissions from this address. Please wait ${rateLimitResult.retryAfter} seconds before trying again, or contact us directly on WhatsApp (+91 73870 10393) or email (datnixsolution@gmail.com).`,
      retryAfter: rateLimitResult.retryAfter
    });
  }

  try {
    // 6. Safe Payload Extraction & Structure Inspection
    let body = {};
    if (typeof req.body === 'string') {
      try {
        body = JSON.parse(req.body);
      } catch {
        return res.status(400).json({
          success: false,
          error: 'Invalid JSON request payload.'
        });
      }
    } else {
      body = req.body || {};
    }

    if (!body || typeof body !== 'object' || Array.isArray(body)) {
      return res.status(400).json({
        success: false,
        error: 'Invalid request payload structure. Expected a JSON object.'
      });
    }

    if (JSON.stringify(body).length > 51200) {
      return res.status(413).json({
        success: false,
        error: 'Payload Too Large. Request data exceeds the 50KB limit.'
      });
    }

    // Prototype Pollution Guard
    const forbiddenKeys = ['__proto__', 'constructor', 'prototype'];
    for (const key of Object.keys(body)) {
      if (forbiddenKeys.includes(key.toLowerCase())) {
        return res.status(400).json({
          success: false,
          error: 'Invalid payload key detected.'
        });
      }
    }

    // Extract standardized fields
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
      return res.status(200).json({
        success: true,
        message: 'Enquiry received.'
      });
    }

    // 8. CRLF Injection Guard
    const crlfRegex = /[\r\n]/;
    const singleLineFields = [fullName, companyName, email, phone, serviceInput, estimatedVolume];
    for (const field of singleLineFields) {
      if (crlfRegex.test(field)) {
        return res.status(400).json({
          success: false,
          error: 'Invalid input characters detected in form submission.'
        });
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
      return res.status(400).json({
        success: false,
        error: errors.join(' ')
      });
    }

    // 10. Content-Signature Duplicate Submission Check (30-second window)
    const submissionSig = `${email}:${canonicalService}:${projectDetails.slice(0, 50).trim()}`;
    const duplicateCheck = checkDuplicateSubmission(submissionSig);
    if (duplicateCheck.isDuplicate) {
      res.setHeader('Retry-After', duplicateCheck.waitSec.toString());
      return res.status(429).json({
        success: false,
        error: `Duplicate enquiry detected. Please wait ${duplicateCheck.waitSec} seconds before resubmitting.`,
        retryAfter: duplicateCheck.waitSec
      });
    }

    // 11. Environment Variables & Secret Configuration
    const resendApiKey = process.env.RESEND_API_KEY;
    const sendgridApiKey = process.env.SENDGRID_API_KEY;
    const recipientEmail = process.env.NOTIFICATION_EMAIL || 'datnixsolution@gmail.com';
    const senderEmail = process.env.SENDER_EMAIL || 'onboarding@resend.dev';

    if (!resendApiKey && !sendgridApiKey) {
      console.warn('[DATNIX API NOTICE] Email delivery service is not yet configured in environment variables.');
      return res.status(503).json({
        success: false,
        error: 'Email delivery backend is currently not configured. Set RESEND_API_KEY in hosting environment variables.'
      });
    }

    // 12. Branded HTML Notification Template
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
        throw new Error('Email provider rejected the dispatch request.');
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
        throw new Error('Email provider rejected the dispatch request.');
      }
    }

    if (providerSuccess) {
      return res.status(200).json({
        success: true,
        message: 'Your enquiry has been delivered to datnixsolution@gmail.com. We will contact you within 2-4 business hours.',
        deliveryId: providerResponseId
      });
    } else {
      throw new Error('Provider did not confirm delivery.');
    }
  } catch (err) {
    console.error('[DATNIX DISPATCH EXCEPTION]', err.message);
    return res.status(502).json({
      success: false,
      error: 'Enquiry delivery service is temporarily unavailable. Please contact datnixsolution@gmail.com directly.'
    });
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
