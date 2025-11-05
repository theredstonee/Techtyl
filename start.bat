@echo off
echo ========================================
echo   Techtyl by Pterodactyl - Starter
echo ========================================
echo.

REM Check if backend/.env exists
if not exist "backend\.env" (
    echo [ERROR] backend/.env nicht gefunden!
    echo Bitte zuerst Setup durchfuehren:
    echo   cd backend
    echo   copy .env.example .env
    echo   notepad .env
    echo   php artisan key:generate
    echo   php artisan migrate
    pause
    exit /b 1
)

REM Check if frontend/node_modules exists
if not exist "frontend\node_modules" (
    echo [INFO] Frontend-Dependencies werden installiert...
    cd frontend
    call npm install
    cd ..
)

echo [OK] Starte Backend und Frontend...
echo.
echo Backend: http://localhost:8000
echo Frontend: http://localhost:3000
echo.
echo Druecke Ctrl+C um beide zu stoppen
echo.

REM Start backend in new window
start "Techtyl Backend" cmd /k "cd backend && php artisan serve"

REM Wait a bit
timeout /t 3 /nobreak >nul

REM Start frontend in new window
start "Techtyl Frontend" cmd /k "cd frontend && npm run dev"

echo.
echo [OK] Techtyl wurde gestartet!
echo.
echo Oeffne im Browser: http://localhost:3000
echo.
pause
