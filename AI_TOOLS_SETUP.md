# AI TOOLS CONFIGURATION GUIDE
**Date:** 2026-02-14  
**Server:** technostationery.com Production

---

## ✅ INSTALLED TOOLS

### 1. Gemini CLI (v0.28.2)
**Location:** `/usr/bin/gemini`  
**Status:** ✅ Installed

### 2. Aider (v0.82.3)
**Location:** `/usr/local/bin/aider`  
**Status:** ✅ Installed and Working

---

## 🔑 API KEYS CONFIGURATION

All API keys are stored in: `~/.gemini_config`

```bash
# Gemini API Keys
export GEMINI_API_KEY=AIzaSyAuqKlzckc3L0bMd3Fr7MAFERCAEeTUR4k
export GOOGLE_API_KEY=AIzaSyB5y2VMS_D7ykoo9Mkn6vv6T2VbGKULhtc

# Anthropic API Keys
export ANTHROPIC_API_KEY=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

# OpenAI API Keys
export OPENAI_API_KEY=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
export OPENAI_BASE_URL=https://api.openai.com/v1
```

**Auto-load:** Added to `~/.bashrc` (loads automatically on login)

---

## 🚀 USAGE EXAMPLES

### Aider with Gemini (WORKING ✅)
```bash
# Load API keys (if not auto-loaded)
source ~/.gemini_config

# Use aider with Gemini 1.5 Pro
aider --model gemini/gemini-1.5-pro

# Quick command
aider --model gemini/gemini-1.5-pro --message "your prompt here"

# With specific files
aider --model gemini/gemini-1.5-pro file1.php file2.php
```

### Gemini CLI
```bash
# List available models
gemini models list

# Chat with Gemini
gemini chat

# Generate content
gemini generate "Your prompt here"
```

### Aider with Claude (Anthropic)
```bash
aider --model claude-3-5-sonnet-20241022

# Or shorter
aider --model sonnet
```

### Aider with OpenAI
```bash
aider --model gpt-4o

# Or GPT-4
aider --model gpt-4
```

---

## 📊 TESTED CONFIGURATIONS

| Tool | Model | Status | Notes |
|------|-------|--------|-------|
| Aider | gemini/gemini-1.5-pro | ✅ Working | Tested successfully |
| Aider | claude-3-5-sonnet | ⏳ Not tested | API key configured |
| Aider | gpt-4o | ⏳ Not tested | API key configured |
| Gemini CLI | Default | ⚠️ Limited | Some features restricted |

---

## 🔧 TROUBLESHOOTING

### If API keys are not loaded:
```bash
source ~/.gemini_config
```

### To verify API keys are set:
```bash
echo $GEMINI_API_KEY
echo $ANTHROPIC_API_KEY
echo $OPENAI_API_KEY
```

### If aider doesn't find model:
```bash
# List available models
aider --models

# Use full model name
aider --model gemini/gemini-1.5-pro
```

### For large repositories (like this one):
```bash
# Use subtree-only mode to avoid scanning all 12,680 files
aider --subtree-only --model gemini/gemini-1.5-pro

# Or create .aiderignore file to exclude directories
```

---

## 📁 IMPORTANT FILES

- **API Config:** `~/.gemini_config`
- **Bashrc:** `~/.bashrc` (auto-loads config)
- **Aider Config:** `.aider.conf.yml` (create if needed)
- **Git Ignore:** `.aiderignore` (aider-specific ignores)

---

## ⚙️ RECOMMENDED SETTINGS FOR THIS REPO

Create `.aider.conf.yml`:
```yaml
# Aider configuration for Magento repository
model: gemini/gemini-1.5-pro
auto-commits: false
dirty-commits: false
subtree-only: true
```

Create `.aiderignore`:
```
# Ignore vendor and generated files
vendor/
generated/
var/
pub/static/
node_modules/
*.min.js
*.min.css
```

---

## 🎯 NEXT STEPS

1. ✅ **API Keys Configured** - All API keys are set and loaded automatically
2. ✅ **Aider Tested** - Working with Gemini 1.5 Pro
3. ⏳ **Test Other Models** - Try Claude and GPT-4 if needed
4. ⏳ **Optimize for Repo Size** - Create .aiderignore for better performance

---

## 📝 ALTERNATIVE TOOLS TO INSTALL

If you want additional AI coding tools:

### Cursor (VSCode Fork with AI)
```bash
# Download and install from https://cursor.sh/
```

### GitHub Copilot CLI
```bash
npm install -g @githubnext/github-copilot-cli
```

### Continue.dev
```bash
# VSCode extension - install from marketplace
```

---

**Status:** ✅ AI Tools Configured and Working  
**Primary Tool:** Aider with Gemini 1.5 Pro  
**Auto-load:** API keys load automatically on login
