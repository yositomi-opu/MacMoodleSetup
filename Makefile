.NOTPARALLEL:
ifeq ($(OS),Windows_NT)
PYTHON ?= py -3
RUN_MANAGER = $(PYTHON) scripts/manage.py
else
RUN_MANAGER = bash scripts/manage.sh
endif

.PHONY: setup start stop restart check logs status open credentials backup
setup start stop restart check logs status open credentials backup:
	@$(RUN_MANAGER) $@
