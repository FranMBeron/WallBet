.PHONY: dev dev-back dev-front install migrate seed test snapshots fresh

# Arranca backend y frontend en paralelo
dev:
	make -j2 dev-back dev-front

dev-back:
	cd backend && php artisan serve

dev-front:
	cd frontend && npm run dev

# Instalar dependencias
install:
	cd backend && composer install
	cd frontend && npm install

# Database
migrate:
	cd backend && php artisan migrate

seed:
	cd backend && php artisan db:seed

fresh:
	cd backend && php artisan migrate:fresh --seed

# Tests
test:
	cd backend && php artisan test

test-front:
	cd frontend && npm test

# Snapshots manual trigger
snapshots:
	cd backend && php artisan snapshots:capture
