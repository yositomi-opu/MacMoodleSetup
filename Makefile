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

# Separate Moodle 5.3 site; legacy targets and data remain unchanged.
ifeq ($(OS),Windows_NT)
PYTHON53 ?= py -3
else
PYTHON53 ?= python3
endif
.PHONY: setup53 start53 stop53 restart53 check53 logs53 status53 open53 credentials53 backup53 doctor53
setup53 start53 stop53 restart53 check53 logs53 status53 open53 credentials53 backup53 doctor53:
	@$(PYTHON53) scripts/manage.py $@
