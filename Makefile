test:
	vendor/bin/phpunit

coverage:
	php -d xdebug.mode=coverage vendor/bin/phpunit --display-deprecations --coverage-clover=build/logs/clover.xml

analyze:
	vendor/bin/psalm --no-cache