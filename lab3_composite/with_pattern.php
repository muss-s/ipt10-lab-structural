<?php
// Lab 3 - WITH Composite (GOOD): leaves and groups share one component interface.

interface ForumComponent {
    public function display(int $depth = 0): void;
}

class Post implements ForumComponent {
    public function __construct(private string $author, private string $message) {}
    public function display(int $depth = 0): void {
        $indent = str_repeat("  ", $depth);
        echo $indent . "- Post by {$this->author}: {$this->message}\n";
    }
}

class Thread implements ForumComponent {
    /** @var ForumComponent[] */
    private array $children = [];
    public function __construct(private string $title) {}
    public function add(ForumComponent $c): void { $this->children[] = $c; }
    public function display(int $depth = 0): void {
        echo str_repeat("  ", $depth) . "+ Thread: {$this->title}\n";
        foreach ($this->children as $child) {
            $child->display($depth + 1);
        }
    }
    public static function fromApi(int $postId): self {
        $postJson = @file_get_contents("https://jsonplaceholder.typicode.com/posts/$postId");
        if ($postJson === false) throw new RuntimeException("Post API unavailable.");
        $post = json_decode($postJson, true);
        if (!is_array($post)) throw new RuntimeException("Invalid post response.");
        $thread = new self($post["title"]);
        $thread->add(new Post("Author {$post['userId']}", substr($post["body"], 0, 40) . "..."));

        $commentsJson = @file_get_contents("https://jsonplaceholder.typicode.com/posts/$postId/comments");
        if ($commentsJson === false) throw new RuntimeException("Comments API unavailable.");
        $comments = json_decode($commentsJson, true);
        if (!is_array($comments)) throw new RuntimeException("Invalid comments response.");
        $replies = new Thread("Replies");
        foreach (array_slice($comments, 0, 2) as $c) {
            $replies->add(new Post($c["email"], substr($c["body"], 0, 30) . "..."));
        }
        $thread->add($replies);
        return $thread;
    }
}

// RAG-style Composite: a Document contains Sections, while Sections can contain
// nested Sections and Chunks. All components expose getText() and embed().
interface RagComponent {
    public function getText(): string;
    public function embed(): array;
}

class Chunk implements RagComponent {
    public function __construct(private string $text) {}
    public function getText(): string { return $this->text; }
    public function embed(): array {
        // Deterministic toy embedding for the laboratory; replace with an actual
        // embedding-model/API call in a production RAG system.
        $vector = [];
        foreach (str_split(strtolower($this->text)) as $char) {
            if (ctype_alpha($char)) $vector[ord($char) - ord('a')] = ($vector[ord($char) - ord('a')] ?? 0) + 1;
        }
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        for ($i = 0; $i < 26; $i++) $vector[$i] = $norm > 0 ? (($vector[$i] ?? 0) / $norm) : 0.0;
        return $vector;
    }
}

class Section implements RagComponent {
    /** @var RagComponent[] */
    private array $children = [];
    public function __construct(private string $title) {}
    public function add(RagComponent $component): void { $this->children[] = $component; }
    public function getText(): string {
        $parts = [$this->title];
        foreach ($this->children as $child) $parts[] = $child->getText();
        return implode("\n", $parts);
    }
    public function embed(): array {
        return (new Chunk($this->getText()))->embed();
    }
}

class Document implements RagComponent {
    /** @var RagComponent[] */
    private array $sections = [];
    public function __construct(private string $title) {}
    public function add(RagComponent $section): void { $this->sections[] = $section; }
    public function getText(): string {
        $parts = [$this->title];
        foreach ($this->sections as $section) $parts[] = $section->getText();
        return implode("\n", $parts);
    }
    public function embed(): array {
        return (new Chunk($this->getText()))->embed();
    }
}

if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Composite (GOOD - complete TODOs):\n";
    try {
        $thread = Thread::fromApi(1);
        $thread->display();
    } catch (Throwable $e) {
        echo "Live forum demo skipped: {$e->getMessage()}\n";
    }

    $document = new Document('RAG Demo Document');
    $section = new Section('Information Management');
    $section->add(new Chunk('A database stores structured information.'));
    $section->add(new Chunk('Indexes improve query performance.'));
    $document->add($section);

    echo "\nRAG Composite text:\n";
    echo $document->getText() . "\n";
    echo "Embedding dimensions: " . count($document->embed()) . "\n";
}
