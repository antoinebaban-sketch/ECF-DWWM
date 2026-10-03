<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Role;

/**
 * Entité Utilisateur (client, employé ou administrateur). Porte les règles
 * liées au compte : droit de connexion, suppression RGPD, désactivation.
 */
final class Utilisateur
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $prenom,
        public readonly string $nom,
        public readonly Role $role,
        public readonly bool $actif = true,
        private readonly string $passwordHash = '',
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['utilisateur_id'],
            email: (string) ($row['email'] ?? ''),
            prenom: (string) ($row['prenom'] ?? ''),
            nom: (string) ($row['nom'] ?? ''),
            role: Role::from($row['role']),
            actif: (bool) ($row['statut_compte'] ?? true),
            passwordHash: (string) ($row['password'] ?? ''),
        );
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Règle RGPD : un client peut supprimer son compte lui-même ; un compte du
     * personnel ne peut pas l'être par cette voie.
     */
    public function peutSupprimerSonCompte(): bool
    {
        return !$this->role->estPersonnel();
    }

    /** Règle métier : l'administrateur ne peut jamais être désactivé. */
    public function peutEtreDesactive(): bool
    {
        return $this->role !== Role::Administrateur;
    }

    /** Données conservées en session (et renvoyées au front) après connexion. */
    public function toSession(): array
    {
        return [
            'id'     => $this->id,
            'email'  => $this->email,
            'role'   => $this->role->value,
            'prenom' => $this->prenom,
            'nom'    => $this->nom,
        ];
    }
}
