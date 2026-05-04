OpenRouter setup

This project reads OpenRouter API keys from environment variables to avoid committing secrets.

Required environment variables:
- OPENROUTER_API_KEY — main API key used by the application
- OPENROUTER_MANAGEMENT_KEY — (optional) management key for admin operations

Recommended steps:
1. Locally (bash):
   export OPENROUTER_API_KEY="<paste-your-key-here>"
   export OPENROUTER_MANAGEMENT_KEY="<paste-your-management-key>"

2. In production, configure these in your host's secret manager (Heroku, Vercel, Docker secrets, systemd service file, etc.).

3. Do NOT commit these keys to the repository. If a key was previously committed, rotate it immediately in OpenRouter dashboard.

If you'd like, I can (a) create a .env file locally (not committed) containing your keys, or (b) insert keys into config locally without committing. Say which.