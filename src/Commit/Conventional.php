<?php

namespace ConventionalChangelog\Commit;

use ConventionalChangelog\Helper\Format;
use ConventionalChangelog\Type\Stringable;

class Conventional implements Stringable
{
    protected const PATTERN_HEADER = "/^(?'type'[a-z]+)(\((?'scope'.+)\))?(?'important'[!]?)[:][[:blank:]](?'description'.+)/iums";
    protected const PATTERN_FOOTER = "/(?'token'^([a-z0-9_-]+|BREAKING[[:blank:]]CHANGES?))(?'value'([:][[:blank:]]|[:]?[[:blank:]][#](?=\w)).*?)$/iums";

    /**
     * Raw content.
     *
     * @var string
     */
    protected $raw;

    /**
     * Sha hash.
     *
     * @var string
     */
    protected $hash;

    /**
     * Type.
     *
     * @var Type
     */
    protected $type;

    /**
     * Scope.
     *
     * @var Scope
     */
    protected $scope;

    /**
     * Important.
     *
     * @var bool
     */
    protected $important = false;

    /**
     * Description.
     *
     * @var Description
     */
    protected $description;

    /**
     * @var Body
     */
    protected $body;

    /**
     * Footers.
     *
     * @var Footer[]
     */
    protected $footers = [];

    public function __construct(string $commit)
    {
        $this->raw = Format::clean($commit);

        if (!$this->isValid()) {
            return;
        }

        $rows = explode("\n", $commit);
        $count = count($rows);
        // Commit info
        $hash = trim($rows[$count - 1]);
        if ($this->isValidHash($hash)) {
            $this->hash = $hash;
        }
        $header = $rows[0];
        $message = '';
        // Get message
        foreach ($rows as $i => $row) {
            if ($i !== 0 && $i !== $count) {
                $message .= $row . "\n";
            }
        }
        $this->parseHeader($header);
        $this->parseMessage($message);
    }

    /**
     * Parse header.
     */
    protected function parseHeader(string $header)
    {
        preg_match(self::PATTERN_HEADER, $header, $matches);
        $this->type = new Type((string)$matches['type']);
        $this->scope = new Scope((string)$matches['scope']);
        $this->important = !empty($matches['important']) ? true : false;
        $this->description = new Description((string)$matches['description']);
    }

    /**
     * Parse message.
     */
    protected function parseMessage(string $message)
    {
        $body = Format::clean($message);
        if (preg_match_all(self::PATTERN_FOOTER, $body, $matches, PREG_SET_ORDER, 0)) {
            foreach ($matches as $match) {
                $footer = $match[0];
                $body = str_replace($footer, '', $body);
                $value = ltrim((string)$match['value'], ':');
                $value = Format::clean($value);
                $this->footers[] = new Footer((string)$match['token'], $value);
            }
        }
        $body = Format::clean($body);
        $this->body = new Body($body);
    }

    /**
     * Check if is valid SHA-1.
     */
    protected function isValidHash(string $hash)
    {
        return (bool)preg_match('/^[0-9a-f]{40}$/i', $hash);
    }

    /**
     * Check if is valid conventional commit.
     */
    public function isValid(): bool
    {
        return (bool)preg_match(self::PATTERN_HEADER, $this->raw);
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getShortHash(): string
    {
        return substr($this->hash, 0, 6);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function getDescription(): Description
    {
        return $this->description;
    }

    public function getBody(): Body
    {
        return $this->body;
    }

    /**
     * @return Footer[]
     */
    public function getFooters(): array
    {
        return $this->footers;
    }

    public function getBreakingChanges(): array
    {
        $messages = [];
        foreach ($this->footers as $footer) {
            if ($footer->getToken() === 'breaking changes') {
                $messages[] = $footer->getValue();
            }
        }

        return $messages;
    }

    /**
     * Get issues references.
     */
    public function getReferences(): array
    {
        $refs = [];
        foreach ($this->footers as $footer) {
            $refs = array_merge($footer->getReferences());
        }

        return array_unique($refs);
    }

    public function getHeader()
    {
        $header = $this->type;
        if (!empty((string)$this->scope)) {
            $header .= '(' . $this->scope . ')';
        }
        if ($this->important) {
            $header .= '!';
        }
        $header .= ': ' . $this->description;

        return $header;
    }

    public function getMessage()
    {
        $footer = implode("\n", $this->footers);

        return $this->body . "\n\n" . $footer;
    }

    public function __toString(): string
    {
        $header = $this->getHeader();
        $message = $this->getMessage();
        $string = $header . "\n\n" . $message;

        return Format::clean($string);
    }
}