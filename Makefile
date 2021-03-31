#
# JBZoo Toolbox - Mock-Server
#
# This file is part of the JBZoo Toolbox project.
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.
#
# @package    Mock-Server
# @license    MIT
# @copyright  Copyright (C) JBZoo.com, All rights reserved.
# @link       https://github.com/JBZoo/Mock-Server
#


ifneq (, $(wildcard ./vendor/jbzoo/codestyle/src/init.Makefile))
    include ./vendor/jbzoo/codestyle/src/init.Makefile
endif


MOCK_SERVER_LOG  = ./build/server.log
MOCK_SERVER_HOST = 0.0.0.0
MOCK_SERVER_PORT = 8089

PHAR_BOX      = $(PHP_BIN) `pwd`/vendor/bin/box.phar
PHAR_FILE     = `pwd`/build/jbzoo-mock-server.phar
PHAR_FILE_BIN = $(PHP_BIN) $(PHAR_FILE)


update: ##@Project Install/Update all 3rd party dependencies
	$(call title,"Install/Update all 3rd party dependencies")
	@echo "Composer flags: $(JBZOO_COMPOSER_UPDATE_FLAGS)"
	@composer update $(JBZOO_COMPOSER_UPDATE_FLAGS)


test-all: ##@Project Run all project tests at once
	@make test
	@make codestyle


restart:
	@make down
	@make up


up:
	@$(PHP_BIN) `pwd`/jbzoo-mock-server \
        --host=$(MOCK_SERVER_HOST)      \
        --port=$(MOCK_SERVER_PORT)      \
        --mocks=tests/mocks             \
        --ansi                          \
        -vvv


up-background:
	@AMP_LOG_COLOR=true make up   \
        1>> "$(MOCK_SERVER_LOG)"  \
        2>> "$(MOCK_SERVER_LOG)"  \
        &


down:
	@-pgrep -f "jbzoo-mock-server"      | xargs kill -15 || true
	@-pgrep -f "jbzoo-mock-server.phar" | xargs kill -15 || true
	@echo "Mock Server killed"


dev-watcher:
	@make down
	@make up-background

bench:
	@apib -1 http://$(MOCK_SERVER_HOST):$(MOCK_SERVER_PORT)/testMinimalMock
	@apib -c 100 -d 10 http://$(MOCK_SERVER_HOST):$(MOCK_SERVER_PORT)/testMinimalMock

phar:
	@wget https://github.com/box-project/box/releases/download/3.9.1/box.phar \
        --output-document="$(PATH_ROOT)/vendor/bin/box.phar"                  \
        --no-clobber                                                          \
        --no-check-certificate                                                \
        --quiet                                                               || true
	@$(PHAR_BOX) --version
	@$(PHAR_BOX) validate `pwd`/box.json.dist
	@$(PHAR_BOX) compile --working-dir="`pwd`" --with-docker -vvv
	@$(PHAR_BOX) info $(PHAR_FILE) --metadata


phar-test:
	@make down
	@$(PHAR_FILE_BIN)               \
        --host=$(MOCK_SERVER_HOST)  \
        --port=$(MOCK_SERVER_PORT)  \
        --mocks=tests/mocks         \
        --ansi                      \
        -vvv
