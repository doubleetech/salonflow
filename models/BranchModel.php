<?php

/**
 * BranchModel
 * Talks to the `branches` table.
 * Note: branches are never deleted, only disabled (status = 'disabled'),
 * same "never truly delete" rule the spec applies to user accounts —
 * this keeps historical transactions pointing at something real.
 */
class BranchModel
{
    public static function all(): array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT * FROM branches ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public static function allActive(): array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM branches WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $businessId, string $name, string $address, string $phone): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO branches (business_id, name, address, phone, status)
             VALUES (:business_id, :name, :address, :phone, 'active')"
        );
        $stmt->execute([
            'business_id' => $businessId, 'name' => $name, 'address' => $address, 'phone' => $phone,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, string $address, string $phone): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE branches SET name = :name, address = :address, phone = :phone WHERE id = :id"
        );
        return $stmt->execute(['name' => $name, 'address' => $address, 'phone' => $phone, 'id' => $id]);
    }

    public static function setStatus(int $id, string $status): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE branches SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
