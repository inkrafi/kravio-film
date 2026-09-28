<?php

namespace Tests\Unit;

use App\Support\RoleLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoleLabelTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function roles(): array
    {
        return [
            'tampil sebagai diri sendiri' => ['Self', 'Dirinya sendiri'],
            'himself' => ['Himself', 'Dirinya sendiri'],
            'herself dengan peran acara' => ['Herself - Host', 'Dirinya sendiri – Pembawa acara'],
            'self sebagai tamu' => ['Self - Guest', 'Dirinya sendiri – Bintang tamu'],
            'cuplikan arsip' => ['Self (archive footage)', 'Dirinya sendiri (cuplikan arsip)'],
            'pengisi suara' => ['Hanamichi Sakuragi (voice)', 'Hanamichi Sakuragi (suara)'],
            'tanpa kredit' => ['Waiter (uncredited)', 'Waiter (tanpa kredit)'],
            'istilah utuh' => ['Narrator', 'Narator'],
            'istilah + suara' => ['Narrator (voice)', 'Narator (suara)'],
            'nama tokoh dibiarkan' => ['Walter White', 'Walter White'],
            'nama tokoh berawalan self tidak diubah' => ['Selfridge', 'Selfridge'],
            'kosong' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('roles')]
    public function test_common_tmdb_role_terms_are_translated(?string $role, ?string $expected): void
    {
        $this->assertSame($expected, RoleLabel::translate($role));
    }

    public function test_inside_a_sentence_self_is_lowercase(): void
    {
        $this->assertSame('dirinya sendiri', RoleLabel::translate('Self', inSentence: true));
        $this->assertSame('Walter White', RoleLabel::translate('Walter White', inSentence: true));
    }
}
