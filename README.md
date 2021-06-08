MeiliSearch
===========

[![Build status on GitHub](https://github.com/xp-forge/meilisearch/workflows/Tests/badge.svg)](https://github.com/xp-forge/meilisearch/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/meilisearch/version.png)](https://packagist.org/packages/xp-forge/meilisearch)

Client library for [MeiliSearch](https://www.meilisearch.com/).

Example
-------
```php
use com\meilisearch\MeiliSearch;

$search= new MeiliSearch('http://localhost:7700');

// Search the "content" index for the given term
$result= $search->locate('content')->search($term);

// Output results
Console::writeLine('Found %d hits for "%s" in %.3f seconds', $result->hits(), $term, $result->elapsedTime());
foreach ($result as $document) {
  Console::writeLine('- ', $document);
}
```