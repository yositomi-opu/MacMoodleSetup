.NOTPARALLEL:
.PHONY: setup start stop restart check logs status open credentials backup
setup start stop restart check logs status open credentials backup:
	@bash scripts/manage.sh $@
