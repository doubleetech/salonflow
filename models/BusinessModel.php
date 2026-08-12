<?php

/**
 * BusinessModel
 * Talks to the `businesses` table. In practice there's only ever one
 * row (one salon), so this model is simpler than the others — get it,
 * update it, done.
 */
class BusinessModel
{
    public static function get(): ?array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT * FROM businesses ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, string $name, string $phone, string $address, string $currency, ?string $logoPath = null): bool
    {
        $db = Database::connect();

        if ($logoPath !== null) {
            $stmt = $db->prepare(
                "UPDATE businesses SET name = :name, phone = :phone, address = :address, currency = :currency, logo_path = :logo WHERE id = :id"
            );
            return $stmt->execute([
                'name' => $name, 'phone' => $phone, 'address' => $address,
                'currency' => $currency, 'logo' => $logoPath, 'id' => $id,
            ]);
        }

        $stmt = $db->prepare(
            "UPDATE businesses SET name = :name, phone = :phone, address = :address, currency = :currency WHERE id = :id"
        );
        return $stmt->execute([
            'name' => $name, 'phone' => $phone, 'address' => $address,
            'currency' => $currency, 'id' => $id,
        ]);
    }
}
