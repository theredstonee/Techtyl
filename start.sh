#!/bin/bash

echo "========================================"
echo "  Techtyl by Pterodactyl - Starter"
echo "========================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if backend/.env exists
if [ ! -f "backend/.env" ]; then
    echo -e "${RED}[ERROR]${NC} backend/.env nicht gefunden!"
    echo "Bitte zuerst Setup durchführen:"
    echo "  cd backend"
    echo "  cp .env.example .env"
    echo "  nano .env"
    echo "  php artisan key:generate"
    echo "  php artisan migrate"
    exit 1
fi

# Check if frontend/node_modules exists
if [ ! -d "frontend/node_modules" ]; then
    echo -e "${YELLOW}[INFO]${NC} Frontend-Dependencies werden installiert..."
    cd frontend
    npm install
    cd ..
fi

echo -e "${GREEN}[OK]${NC} Starte Backend und Frontend..."
echo ""
echo "Backend: http://localhost:8000"
echo "Frontend: http://localhost:3000"
echo ""
echo "Drücke Ctrl+C um beide zu stoppen"
echo ""

# Function to cleanup on exit
cleanup() {
    echo ""
    echo "Stoppe Server..."
    kill $BACKEND_PID $FRONTEND_PID 2>/dev/null
    exit 0
}

trap cleanup INT TERM

# Start backend
cd backend
php artisan serve > ../backend.log 2>&1 &
BACKEND_PID=$!
cd ..

# Wait a bit
sleep 3

# Start frontend
cd frontend
npm run dev > ../frontend.log 2>&1 &
FRONTEND_PID=$!
cd ..

echo -e "${GREEN}[OK]${NC} Techtyl wurde gestartet!"
echo ""
echo "Öffne im Browser: http://localhost:3000"
echo ""
echo "Logs anzeigen:"
echo "  Backend:  tail -f backend.log"
echo "  Frontend: tail -f frontend.log"
echo ""

# Wait for both processes
wait $BACKEND_PID $FRONTEND_PID
