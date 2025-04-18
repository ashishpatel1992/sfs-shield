.PHONY: install test lint fix bump-version

install:
	composer install

test:
	vendor/bin/phpunit

lint:
	vendor/bin/phpcs --standard=WordPress src/

fix:
	vendor/bin/phpcbf --standard=WordPress src/

bump-version:
	@echo "Usage: make bump-version NEW=1.0.1"
	sed -i "s/\"version\": \".*\"/\"version\": \"$(NEW)\"/" composer.json
	sed -i "s/^Stable tag: .*/Stable tag: $(NEW)/" README.md
	sed -i "s/^Version: .*/Version: $(NEW)/" stopforumspam-registration.php