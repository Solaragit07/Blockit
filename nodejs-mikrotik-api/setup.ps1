#!/usr/bin/env pwsh

Write-Host "🚀 Setting up Node.js MikroTik API Server" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green

# Check if Node.js is installed
$nodeVersion = $null
try {
    $nodeVersion = node --version 2>$null
} catch {
    $nodeVersion = $null
}

if ($nodeVersion) {
    Write-Host "✅ Node.js is already installed: $nodeVersion" -ForegroundColor Green
} else {
    Write-Host "❌ Node.js is not installed" -ForegroundColor Red
    Write-Host "📥 Please install Node.js first:" -ForegroundColor Yellow
    Write-Host "   1. Go to: https://nodejs.org/" -ForegroundColor Yellow
    Write-Host "   2. Download LTS version" -ForegroundColor Yellow
    Write-Host "   3. Run installer" -ForegroundColor Yellow
    Write-Host "   4. Restart PowerShell" -ForegroundColor Yellow
    Write-Host "   5. Run this script again" -ForegroundColor Yellow
    
    # Open Node.js website
    Start-Process "https://nodejs.org/"
    
    Read-Host "Press Enter to continue after installing Node.js..."
    exit 1
}

# Check if npm is available
$npmVersion = $null
try {
    $npmVersion = npm --version 2>$null
} catch {
    $npmVersion = $null
}

if ($npmVersion) {
    Write-Host "✅ npm is available: $npmVersion" -ForegroundColor Green
} else {
    Write-Host "❌ npm is not available" -ForegroundColor Red
    exit 1
}

# Install dependencies
Write-Host "📦 Installing dependencies..." -ForegroundColor Blue
try {
    npm install express cors routeros-api dotenv nodemon
    Write-Host "✅ Dependencies installed successfully!" -ForegroundColor Green
} catch {
    Write-Host "❌ Failed to install dependencies" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# Test if all files exist
$requiredFiles = @(
    "package.json",
    ".env",
    "server.js",
    "mikrotik-connector.js",
    "README.md"
)

Write-Host "🔍 Checking required files..." -ForegroundColor Blue
foreach ($file in $requiredFiles) {
    if (Test-Path $file) {
        Write-Host "✅ $file exists" -ForegroundColor Green
    } else {
        Write-Host "❌ $file missing" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "🎉 Setup Complete!" -ForegroundColor Green
Write-Host "==================" -ForegroundColor Green
Write-Host "To start the server:" -ForegroundColor Yellow
Write-Host "   npm start" -ForegroundColor White
Write-Host ""
Write-Host "For development mode:" -ForegroundColor Yellow
Write-Host "   npm run dev" -ForegroundColor White
Write-Host ""
Write-Host "Test endpoints:" -ForegroundColor Yellow
Write-Host "   http://localhost:3000/test" -ForegroundColor White
Write-Host "   http://localhost:3000/devices" -ForegroundColor White
Write-Host "   http://localhost:3000/devices/simple" -ForegroundColor White
Write-Host ""

# Ask if user wants to start the server now
$startNow = Read-Host "Start the server now? (y/n)"
if ($startNow -eq "y" -or $startNow -eq "Y" -or $startNow -eq "yes") {
    Write-Host "🚀 Starting Node.js server..." -ForegroundColor Green
    npm start
}
