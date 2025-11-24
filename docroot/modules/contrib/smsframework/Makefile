phpstan-baseline:
	php -d memory_limit=-1 ./vendor/bin/phpstan analyze --memory-limit=-1 --generate-baseline=./phpstan-baseline.neon --allow-empty-baseline

phpcs:
	vendor/bin/phpcs

phpcbf:
	vendor/bin/phpcbf

phpstan:
	php -d memory_limit=-1 ./vendor/bin/phpstan analyze

lint: phpcs phpstan

fix: phpcbf

clean:
	rm -rf vendor
	rm -rf app
	rm -rf modules
	rm -rf core
	rm -rf web
