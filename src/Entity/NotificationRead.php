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
 * Entity class for "notification_reads" table
 */
#[Entity]
#[Table(schema: "uac", name: "notification_reads")]
class NotificationRead extends AbstractEntity
{
    #[Id]
    #[Column(name: "notification_id", type: "string")]
    private string $notificationId;

    #[Id]
    #[Column(name: "user_id", type: "integer")]
    private int $userId;

    #[Column(name: "read_at", type: "datetimetz")]
    private DateTime $readAt;

    public function __construct(string $notificationId, int $userId)
    {
        $this->notificationId = $notificationId;
        $this->userId = $userId;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function setNotificationId(string $value): static
    {
        $this->notificationId = $value;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function getReadAt(): DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(DateTime $value): static
    {
        $this->readAt = $value;
        return $this;
    }
}
