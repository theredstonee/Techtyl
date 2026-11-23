#!/usr/bin/env bash
# Liest Nest/Egg-Namen aus dem lokalen Test-Panel (nur lesend).
cd ~/techtyl-testpanel || exit 1
docker compose exec -T panel php artisan tinker --execute='echo json_encode(\Pterodactyl\Models\Egg::with("nest")->get()->map(fn($e)=>[$e->nest->name,$e->name]));'
