atick\Ticker
============

Asynchronnous resource handling, optionally (ab)using ticks

**Example with ticks:**

```PHP
declare(ticks=1);

$conn = new \pq\Connection;
$conn->execAsync("SELECT * FROM foo", function ($rs) {
    var_dump($rs);
});

$ticker = new \atick\Ticker;
$ticker->register();
$ticker->read($conn->socket, function($fd) use ($conn) {
    $conn->poll();
    if ($conn->busy) {
        return false;
    }
    $conn->getResult();
    return true;
});

while (count($ticker));
```

**And an example without ticks:**

```php
$conn = new \pq\Connection;
$conn->execAsync("SELECT * FROM foo", function ($r) {
    var_dump($r);
});

$ticker = new \atick\Ticker;
$ticker->read($conn->socket, function($fd) use ($conn) {
    $conn->poll();
    if ($conn->busy) {
        return false;
    }
    $conn->getResult();
    return true;
});

while($ticker());
```
