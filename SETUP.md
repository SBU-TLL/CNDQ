# CNDQ Local Setup Guide

This project is designed to run in a subdirectory structure to maintain parity with the production environment.

## Directory Structure
```
CNDQ_localroot/
├── .env (Local config)
└── CNDQ/ (This repository)
```

## Quick Setup

### macOS (Valet/Herd)
1. Open your terminal.
2. Navigate to the `CNDQ` directory.
3. Run the setup script:
   ```bash
   ./setup_mac.sh
   ```

### Windows (Herd)
1. Open Command Prompt **as Administrator**.
2. Navigate to the `CNDQ` directory.
3. Run the setup script:
   ```cmd
   setup_windows.bat
   ```

## Post-Setup
- The scripts will automatically create a `.env` file in the parent directory.
- For **macOS Valet**, the site will be available at `https://cndq.test/CNDQ/www/`.
- For **Herd (Windows/Mac)**, ensure the `CNDQ_localroot` folder is added to your Herd 'Paths'.

## Authentication
Local development uses a bypass for Shibboleth. You can switch between test users by visiting:
`http://your-site.test/CNDQ/dev.php`
