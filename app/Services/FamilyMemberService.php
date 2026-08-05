<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyMemberService
{
    public function getRelationship(string $role, string $sex): string
    {
        return match (strtoupper(trim($role))) {
            'PARENT' => $sex === 'MALE' ? 'FATHER' : 'MOTHER',
            'CHILD' => $sex === 'MALE' ? 'SON' : 'DAUGHTER',
            'GRANDPARENT' => $sex === 'MALE' ? 'GRANDFATHER' : 'GRANDMOTHER',
            'GRANDCHILD' => $sex === 'MALE' ? 'GRANDSON' : 'GRANDDAUGHTER',
            'SIBLING' => 'SIBLING',
            'SPOUSE' => 'SPOUSE',
            default => strtoupper(trim($role)),
        };
    }

    public function getInverseRelationship(string $relationship, string $otherSex): string
    {
        return match (strtoupper(trim($relationship))) {
            'FATHER', 'MOTHER' => $otherSex === 'MALE' ? 'SON' : 'DAUGHTER',
            'SON', 'DAUGHTER' => $otherSex === 'MALE' ? 'FATHER' : 'MOTHER',
            'GRANDFATHER', 'GRANDMOTHER' => $otherSex === 'MALE' ? 'GRANDSON' : 'GRANDDAUGHTER',
            'GRANDSON', 'GRANDDAUGHTER' => $otherSex === 'MALE' ? 'GRANDFATHER' : 'GRANDMOTHER',
            'SIBLING' => 'SIBLING',
            'SPOUSE' => 'SPOUSE',
            default => strtoupper(trim($relationship)),
        };
    }

    public function link(Client $parent, int $memberId, string $relationship, User $actor): void
    {
        DB::transaction(function () use ($parent, $memberId, $relationship, $actor) {
            $newMember = Client::query()->findOrFail($memberId);

            $label = $this->getRelationship($relationship, $newMember->sex);
            $inverse = $this->getInverseRelationship($label, $parent->sex);

            FamilyMember::query()->firstOrCreate([
                'client_id' => $parent->id,
                'relative_id' => $memberId,
            ], ['relationship' => $label]);

            FamilyMember::query()->firstOrCreate([
                'client_id' => $memberId,
                'relative_id' => $parent->id,
            ], ['relationship' => $inverse]);

            if ($label === 'SIBLING' && $parent->family_id) {
                $others = Client::query()
                    ->where('family_id', $parent->family_id)
                    ->where('id', '!=', $memberId)
                    ->where('id', '!=', $parent->id)
                    ->pluck('id');

                foreach ($others as $otherId) {
                    FamilyMember::query()->firstOrCreate([
                        'client_id' => $memberId,
                        'relative_id' => $otherId,
                    ], ['relationship' => 'SIBLING']);
                    FamilyMember::query()->firstOrCreate([
                        'client_id' => $otherId,
                        'relative_id' => $memberId,
                    ], ['relationship' => 'SIBLING']);
                }
            }

            $this->audit($actor->id, $parent->id, $memberId, $label);
        });
    }

    private function audit(int $userId, int $parentId, int $memberId, string $label): void
    {
        app(AuditService::class)->log(
            $userId,
            'ADD_FAMILY_MEMBER',
            'tbl_family_members',
            null,
            null,
            json_encode([
                'client_id' => $parentId,
                'relative_id' => $memberId,
                'relationship' => $label,
            ]),
        );
    }
}
