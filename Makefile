test:
	./vendor/bin/phpunit ./tests/LoaderTest.php
lint:
	./vendor/bin/phpcs --standard=PSR12 ./src* ./tests/LoaderTest.php ./extra/*