// PM2 process definition for production.
//
// The queue worker is listed alongside the socket server because socket
// delivery runs through Laravel's queue: without a worker, events are written
// but never sent.
module.exports = {
  apps: [
    {
      name: 'readyroute-socket',
      cwd: __dirname,
      script: 'server.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_memory_restart: '256M',
      env: { NODE_ENV: 'production' },
    },
    {
      name: 'readyroute-queue',
      cwd: __dirname + '/..',
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work --sleep=1 --tries=3 --max-time=3600',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
    },
  ],
}
