
# PHPDOC_ARCHIVE is overridden in the CircleCI release
PHPDOC_ARCHIVE := $(shell pwd)/build/phpDocumentor.phar
OUTPUT_DIR := $(shell pwd)/build
PHPDOC_URL=https://www.phpdoc.org/phpDocumentor.phar

.PHONY: install html

html: install
	rm -rf build/temp build/html
	cd .. && php $(PHPDOC_ARCHIVE) --visibility public --title "LaunchDarkly PHP SDK ${LD_RELEASE_VERSION}"

install: $(PHPDOC_ARCHIVE)

$(PHPDOC_ARCHIVE):
	mkdir -p build
	cd build && unzip ../lib/phpDocumentor.phar.zip
