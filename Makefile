DOCKER ?= docker
CONTAINER_USER ?= $(shell id -u):$(shell id -g)

scaffold:
	$(DOCKER) run \
	  --volume .:/project \
	  --user $(CONTAINER_USER) \
	  --interactive --tty --rm \
	  --pull always \
	  ghcr.io/phpyh/scaffolder:latest \
	  --package-vendor-default thesis \
	  --package-project-default '$(shell basename $$(pwd))' \
	  --php-constraint-default '^8.4' \
	  --authors-default '[{"name":"kafkiansky","email":"vadimzanfir@gmail.com"},{"name":"Valentin Udaltsov","email":"udaltsov.valentin@gmail.com"},{"name":"Thesis Team","homepage":"https://github.com/orgs/thesis-php/people"}]' \
	  --copyright-holder-default 'Valentin Udaltsov'
	git add --all 2>/dev/null || true
.PHONY: scaffold

.DEFAULT_GOAL := scaffold
