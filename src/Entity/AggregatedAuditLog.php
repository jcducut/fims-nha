<?php

namespace PHPMaker2024\FIMS\Entity;

use DateTime;
use DateTimeImmutable;
use DateInterval;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\SequenceGenerator;
use Doctrine\DBAL\Types\Types;
use PHPMaker2024\FIMS\AbstractEntity;
use PHPMaker2024\FIMS\AdvancedSecurity;
use PHPMaker2024\FIMS\UserProfile;
use function PHPMaker2024\FIMS\Config;
use function PHPMaker2024\FIMS\EntityManager;
use function PHPMaker2024\FIMS\RemoveXss;
use function PHPMaker2024\FIMS\HtmlDecode;
use function PHPMaker2024\FIMS\EncryptPassword;

/**
 * Entity class for "aggregated_audit_logs" table
 */
#[Entity]
#[Table(schema: "uac", name: "aggregated_audit_logs")]
class AggregatedAuditLog extends AbstractEntity
{
    #[Column(name: "aggregated_id", type: "integer", nullable: true)]
    private ?int $aggregatedId;

    #[Column(name: "action_date", type: "text", nullable: true)]
    private ?string $actionDate;

    #[Column(type: "string", nullable: true)]
    private ?string $script;

    #[Column(name: "\"user\"", options: ["name" => "user"], type: "string", nullable: true)]
    private ?string $user;

    #[Column(type: "string", nullable: true)]
    private ?string $action;

    #[Column(name: "\"table\"", options: ["name" => "table"], type: "string", nullable: true)]
    private ?string $table;

    #[Column(name: "action_type", type: "text", nullable: true)]
    private ?string $actionType;

    #[Column(type: "text", nullable: true)]
    private ?string $details;

    #[Column(name: "action_count", type: "bigint", nullable: true)]
    private ?string $actionCount;

    public function getAggregatedId(): ?int
    {
        return $this->aggregatedId;
    }

    public function setAggregatedId(?int $value): static
    {
        $this->aggregatedId = $value;
        return $this;
    }

    public function getActionDate(): ?string
    {
        return HtmlDecode($this->actionDate);
    }

    public function setActionDate(?string $value): static
    {
        $this->actionDate = RemoveXss($value);
        return $this;
    }

    public function getScript(): ?string
    {
        return HtmlDecode($this->script);
    }

    public function setScript(?string $value): static
    {
        $this->script = RemoveXss($value);
        return $this;
    }

    public function getUser(): ?string
    {
        return HtmlDecode($this->user);
    }

    public function setUser(?string $value): static
    {
        $this->user = RemoveXss($value);
        return $this;
    }

    public function getAction(): ?string
    {
        return HtmlDecode($this->action);
    }

    public function setAction(?string $value): static
    {
        $this->action = RemoveXss($value);
        return $this;
    }

    public function getTable(): ?string
    {
        return HtmlDecode($this->table);
    }

    public function setTable(?string $value): static
    {
        $this->table = RemoveXss($value);
        return $this;
    }

    public function getActionType(): ?string
    {
        return HtmlDecode($this->actionType);
    }

    public function setActionType(?string $value): static
    {
        $this->actionType = RemoveXss($value);
        return $this;
    }

    public function getDetails(): ?string
    {
        return HtmlDecode($this->details);
    }

    public function setDetails(?string $value): static
    {
        $this->details = RemoveXss($value);
        return $this;
    }

    public function getActionCount(): ?string
    {
        return $this->actionCount;
    }

    public function setActionCount(?string $value): static
    {
        $this->actionCount = $value;
        return $this;
    }
}
