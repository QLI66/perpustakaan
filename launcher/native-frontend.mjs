// Native Frontend Runner for Library System
process.env.PORT = process.env.PORT || '3005';
process.env.NODE_ENV = 'production';
process.env.NUXT_PUBLIC_API_BASE_URL = process.env.NUXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api';

import('../frontend/.output/server/index.mjs');
