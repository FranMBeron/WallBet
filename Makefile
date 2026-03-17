.PHONY: dev stop logs dev-back dev-front install migrate seed test snapshots fresh

BACKEND_LOG  := /tmp/wallbet-backend.log
FRONTEND_LOG := /tmp/wallbet-frontend.log
BACKEND_PID  := /tmp/wallbet-backend.pid
FRONTEND_PID := /tmp/wallbet-frontend.pid

# ── Main dev target ──────────────────────────────────────────────────────────
dev:
	@echo "Starting Docker services (db + redis)..."
	docker compose up -d || true
	@echo ""
	@echo "Starting Laravel backend (logs → $(BACKEND_LOG))..."
	@cd backend && php artisan serve > $(BACKEND_LOG) 2>&1 & echo $$! > $(BACKEND_PID)
	@echo ""
	@echo "Starting Next.js frontend (logs → $(FRONTEND_LOG))..."
	@cd frontend && npm run dev > $(FRONTEND_LOG) 2>&1 & echo $$! > $(FRONTEND_PID)
	@echo ""
	@echo "┌─────────────────────────────────────────┐"
	@echo "│  WallBet is starting up                 │"
	@echo "│                                         │"
	@echo "│  Backend  → http://127.0.0.1:8000       │"
	@echo "│  Frontend → http://localhost:3000        │"
	@echo "│                                         │"
	@echo "│  Run 'make logs' to tail both logs      │"
	@echo "│  Run 'make stop' to shut everything down │"
	@echo "└─────────────────────────────────────────┘"

# ── Stop all services ────────────────────────────────────────────────────────
stop:
	@echo "Stopping background processes..."
	@if [ -f $(BACKEND_PID) ]; then kill $$(cat $(BACKEND_PID)) 2>/dev/null || true; rm -f $(BACKEND_PID); fi
	@if [ -f $(FRONTEND_PID) ]; then kill $$(cat $(FRONTEND_PID)) 2>/dev/null || true; rm -f $(FRONTEND_PID); fi
	@echo "Stopping Docker services..."
	docker compose down || true
	@echo "Done."

# ── Tail logs ────────────────────────────────────────────────────────────────
logs:
	tail -f $(BACKEND_LOG) $(FRONTEND_LOG)

# ── Individual dev targets (kept for direct use) ─────────────────────────────
dev-back:
	cd backend && php artisan serve

dev-front:
	cd frontend && npm run dev

# ── Dependency installation ──────────────────────────────────────────────────
install:
	cd backend && composer install
	cd frontend && npm install

# ── Database ─────────────────────────────────────────────────────────────────
migrate:
	cd backend && php artisan migrate

seed:
	cd backend && php artisan db:seed

fresh:
	cd backend && php artisan migrate:fresh --seed

# ── Tests ────────────────────────────────────────────────────────────────────
test:
	cd backend && php artisan test

test-front:
	cd frontend && npm test

# ── Snapshots ────────────────────────────────────────────────────────────────
snapshots:
	cd backend && php artisan snapshots:capture
