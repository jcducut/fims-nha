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
 * Entity class for "notifications" table
 */
#[Entity]
#[Table(schema: "uac", name: "notifications")]
class Notification extends AbstractEntity
{
    #[Id]
    #[Column(type: "string", unique: true)]
    private string $id;

    #[Column(type: "datetimetz", nullable: true)]
    private ?DateTime $timestamp;

    #[Column(type: "string")]
    private string $type;

    #[Column(type: "string")]
    private string $target;

    #[Column(name: "user_id", type: "integer", nullable: true)]
    private ?int $userId;

    #[Column(type: "string")]
    private string $subject;

    #[Column(type: "text", nullable: true)]
    private ?string $body;

    #[Column(type: "string", nullable: true)]
    private ?string $link;

    #[Column(name: "from_system", type: "string")]
    private string $fromSystem;

    #[Column(name: "is_read", type: "boolean", nullable: true)]
    private ?bool $isRead;

    #[Column(name: "created_at", type: "datetimetz")]
    private DateTime $createdAt;

    #[Column(name: "read_users", type: "text", nullable: true)]
    private ?string $readUsers;

    #[Column(name: "sender_id", type: "integer", nullable: true)]
    private ?int $senderId;

    #[Column(name: "event_key", type: "string", unique: true, nullable: true)]
    private ?string $eventKey;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): static
    {
        $this->id = $value;
        return $this;
    }

    public function getTimestamp(): ?DateTime
    {
        return $this->timestamp;
    }

    public function setTimestamp(?DateTime $value): static
    {
        $this->timestamp = $value;
        return $this;
    }

    public function getType(): string
    {
        return HtmlDecode($this->type);
    }

    public function setType(string $value): static
    {
        $this->type = RemoveXss($value);
        return $this;
    }

    public function getTarget(): string
    {
        return HtmlDecode($this->target);
    }

    public function setTarget(string $value): static
    {
        $this->target = RemoveXss($value);
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function getSubject(): string
    {
        return HtmlDecode($this->subject);
    }

    public function setSubject(string $value): static
    {
        $this->subject = RemoveXss($value);
        return $this;
    }

    public function getBody(): ?string
    {
        return HtmlDecode($this->body);
    }

    public function setBody(?string $value): static
    {
        $this->body = RemoveXss($value);
        return $this;
    }

    public function getLink(): ?string
    {
        return HtmlDecode($this->link);
    }

    public function setLink(?string $value): static
    {
        $this->link = RemoveXss($value);
        return $this;
    }

    public function getFromSystem(): string
    {
        return HtmlDecode($this->fromSystem);
    }

    public function setFromSystem(string $value): static
    {
        $this->fromSystem = RemoveXss($value);
        return $this;
    }

    public function getIsRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(?bool $value): static
    {
        $this->isRead = $value;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $value): static
    {
        $this->createdAt = $value;
        return $this;
    }

    public function getReadUsers(): ?string
    {
        return HtmlDecode($this->readUsers);
    }

    public function setReadUsers(?string $value): static
    {
        $this->readUsers = RemoveXss($value);
        return $this;
    }

    public function getSenderId(): ?int
    {
        return $this->senderId;
    }

    public function setSenderId(?int $value): static
    {
        $this->senderId = $value;
        return $this;
    }

    public function getEventKey(): ?string
    {
        return HtmlDecode($this->eventKey);
    }

    public function setEventKey(?string $value): static
    {
        $this->eventKey = RemoveXss($value);
        return $this;
    }
}
