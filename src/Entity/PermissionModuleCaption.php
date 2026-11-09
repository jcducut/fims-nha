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
 * Entity class for "permission_module_captions" table
 */
#[Entity]
#[Table(schema: "uac", name: "permission_module_captions")]
class PermissionModuleCaption extends AbstractEntity
{
    #[Id]
    #[Column(name: "table_name", type: "string", unique: true)]
    private string $tableName;

    #[Column(type: "string")]
    private string $caption;

    #[Column(name: "is_hidden", type: "boolean")]
    private bool $isHidden;

    #[Column(name: "updated_at", type: "datetimetz")]
    private DateTime $updatedAt;

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $value): static
    {
        $this->tableName = $value;
        return $this;
    }

    public function getCaption(): string
    {
        return HtmlDecode($this->caption);
    }

    public function setCaption(string $value): static
    {
        $this->caption = RemoveXss($value);
        return $this;
    }

    public function getIsHidden(): bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $value): static
    {
        $this->isHidden = $value;
        return $this;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $value): static
    {
        $this->updatedAt = $value;
        return $this;
    }
}
