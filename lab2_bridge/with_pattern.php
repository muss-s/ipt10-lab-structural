<?php
// Lab 2 - WITH Bridge (GOOD): abstraction varies independently from implementation dimensions.

interface DataSource { public function fetch(): array; }

class ApiDataSource implements DataSource
{
    public function fetch(): array
    {
        $json = @file_get_contents("https://jsonplaceholder.typicode.com/posts?_limit=4");
        if ($json === false || trim($json) === "") throw new RuntimeException("Live API unreachable.");
        $data = json_decode($json, true);
        if (!is_array($data)) throw new RuntimeException("Invalid API response.");
        return $data;
    }
}

class FileDataSource implements DataSource
{
    public function fetch(): array
    {
        $json = @file_get_contents(__DIR__ . "/data.json");
        if ($json === false) throw new RuntimeException("data.json not found.");
        $data = json_decode($json, true);
        if (!is_array($data)) throw new RuntimeException("Invalid data.json.");
        return $data;
    }
}

interface Compressor
{
    public function compress(string $content): string;
    public function isCompressed(): bool;
}

class GzipCompressor implements Compressor
{
    public function compress(string $content): string { return gzencode($content, 6); }
    public function isCompressed(): bool { return true; }
}

class NoneCompressor implements Compressor
{
    public function compress(string $content): string { return $content; }
    public function isCompressed(): bool { return false; }
}

abstract class TimesFormatter
{
    public function __construct(protected DataSource $source, protected Compressor $compressor) {}

    public function setCompressor(Compressor $compressor): void
    {
        $this->compressor = $compressor;
    }

    abstract protected function toText(array $records): string;

    public function generate(string $title): string
    {
        $records = $this->source->fetch();
        $body = $this->toText($records);
        return $this->compressor->compress($title . "\n" . $body);
    }
}

class AttendanceTimesFormatter extends TimesFormatter
{
    protected function toText(array $records): string
    {
        $lines = [];
        foreach ($records as $r) {
            $lines[] = "{$r['userId']}  {$r['id']}  " . substr($r['title'] ?? '', 0, 18);
        }
        return implode("\n", $lines);
    }
}

class TimesheetJsonFormatter extends TimesFormatter
{
    protected function toText(array $records): string
    {
        return json_encode($records, JSON_PRETTY_PRINT);
    }
}

function demoBridge(string $name, TimesFormatter $report): void
{
    try {
        $plain = $report->generate($name . ' PLAIN');
        echo "  Plain output bytes: " . strlen($plain) . "\n";
        $report->setCompressor(new GzipCompressor());
        $gzip = $report->generate($name . ' GZIP');
        echo "  Gzip output bytes: " . strlen($gzip) . "\n";
    } catch (Throwable $e) {
        echo "  Demo skipped: {$e->getMessage()}\n";
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Bridge (GOOD - complete TODOs):\n";
    echo "Attendance formatter + File source:\n";
    demoBridge('TIMES', new AttendanceTimesFormatter(new FileDataSource(), new NoneCompressor()));

    echo "Timesheet JSON formatter + File source:\n";
    demoBridge('TIMESHEET', new TimesheetJsonFormatter(new FileDataSource(), new NoneCompressor()));

    echo "Attendance formatter + API source:\n";
    demoBridge('TIMES', new AttendanceTimesFormatter(new ApiDataSource(), new NoneCompressor()));

    echo "Timesheet JSON formatter + API source:\n";
    demoBridge('TIMESHEET', new TimesheetJsonFormatter(new ApiDataSource(), new NoneCompressor()));
}
