import dotenv from 'dotenv'

dotenv.config()

function required(name) {
  const value = process.env[name]

  if (!value || value === 'change-me') {
    // Refusing to boot is safer than running with no shared secret: these
    // endpoints hand out user identity.
    throw new Error(`${name} must be set in socket-server/.env`)
  }

  return value
}

export const config = {
  port: Number(process.env.PORT || 3001),
  laravelUrl: (process.env.LARAVEL_URL || 'http://127.0.0.1:8000').replace(/\/$/, ''),
  secret: required('SOCKET_SECRET'),
  corsOrigins: (process.env.CORS_ORIGINS || '')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean),
  logLevel: process.env.LOG_LEVEL || 'info',
}

export function log(level, message, context = {}) {
  const levels = { error: 0, warn: 1, info: 2, debug: 3 }

  if (levels[level] > levels[config.logLevel]) {
    return
  }

  const line = { time: new Date().toISOString(), level, message, ...context }
  console.log(JSON.stringify(line))
}
