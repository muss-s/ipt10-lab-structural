<?php
// Lab 4 - WITH Decorator (GOOD): behavior can be layered dynamically.

interface HttpClient { public function get(string $url): string; }

class BaseHttpClient implements HttpClient {
    public function get(string $url): string {
        $result = @file_get_contents($url);
        if ($result === false) throw new RuntimeException("GET failed: $url");
        return $result;
    }
}

abstract class HttpDecorator implements HttpClient {
    public function __construct(protected HttpClient $wrapped) {}
}

class LoggingDecorator extends HttpDecorator {
    public function get(string $url): string {
        echo "[Log] GET $url\n";
        $r = $this->wrapped->get($url);
        echo "[Log] Got " . strlen($r) . " bytes\n";
        return $r;
    }
}

class CachingDecorator extends HttpDecorator {
    private array $cache = [];
    public function get(string $url): string {
        if (isset($this->cache[$url])) { echo "[Cache] Hit $url\n"; return $this->cache[$url]; }
        $r = $this->wrapped->get($url);
        $this->cache[$url] = $r;
        echo "[Cache] Stored $url\n";
        return $r;
    }
}

class RetryDecorator extends HttpDecorator {
    public function get(string $url): string {
        $last = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                return $this->wrapped->get($url);
            } catch (Exception $e) {
                $last = $e;
                echo "[Retry] Attempt " . ($i + 1) . " failed\n";
                if ($i < 2) usleep(100000);
            }
        }
        throw $last ?? new RuntimeException('Request failed.');
    }
}

class TokenCounterDecorator extends HttpDecorator {
    public function get(string $url): string {
        $r = $this->wrapped->get($url);
        echo "[Tokens] " . str_word_count($r) . "\n";
        return $r;
    }
}

if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Decorator (GOOD - complete TODOs):\n";
    $client = new BaseHttpClient();
    $client = new RetryDecorator($client);
    $client = new LoggingDecorator($client);
    $client = new CachingDecorator($client);
    $client = new TokenCounterDecorator($client);

    $url = "https://jsonplaceholder.typicode.com/posts/1";
    try {
        echo substr($client->get($url), 0, 60) . "...\n";
        echo substr($client->get($url), 0, 60) . "...\n";
    } catch (Throwable $e) {
        echo "Request failed after retries: {$e->getMessage()}\n";
    }
}
