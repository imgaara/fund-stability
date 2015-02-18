help:
	@echo 'Usage: make (stability|threshold)'

stability: threshold
	php src/stability.php
.PHONY: stability

threshold: bin/threshold

bin/%: src/%.cc
	mkdir -p bin
	c++ -O2 -std=c++0x -o bin/$* src/$*.cc
