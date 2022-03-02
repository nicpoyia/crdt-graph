# CRDT state-based LWW-Element-Graph implementation

## Implementation
Implementation of LWW-Element-Graph is based on the implementation of LWW-Element-Set, i.e. the graph uses 2 LWW-Element-Sets internally (one for vertices and one for edges).

An additional projection/caching mechanism is used internally, in order to balance out the complexity between edits and reads, i.e. the projected state of the graph is available at anytime (according to the known/merged edits). The projected state is partially rebuilt on each merge accordingly (only the affected part).

## Requirements
- PHP 7.4 or greater

## Testing approach
All layers of abstraction are tested independently:
- Element implementation
- Set implementation
- Graph implementation

The "merge" operation is tested both on Set and Graph levels as follows:
- Ensure basic functionality using simple use cases
- Ensure edge cases are handled by simulating the appropriate scenarios
- Ensure commutative property of merge operation
- Ensure associative property of merge operation
- Ensure idempotence property of merge operation

## How to run automated tests
```
# Download Composer executable for your environment (script copied from https://getcomposer.org/download/)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
# Install dependencies
composer install
# Run automated tests with coverage
./vendor/bin/phpunit --coverage-text
```
