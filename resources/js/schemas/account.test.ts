import { describe, expect, it } from 'vite-plus/test';
import { AccountAvatarSchema, AccountPasswordSchema, AccountProfileSchema } from './account';
import { LoginSchema } from './login';

const profile = { name: 'Nama Pengguna', username: 'pengguna', email: 'user@example.test' };
const password = { current_password: 'password', password: 'new-password', password_confirmation: 'new-password' };

describe('account profile', () => {
    it('normalizes email and trims profile fields', () => {
        const result = AccountProfileSchema.validate({
            name: ' Nama Baru ',
            username: ' baru_1 ',
            email: ' USER@Example.Test ',
        });
        expect(result.success).toBe(true);
        expect(result.data).toEqual({ name: 'Nama Baru', username: 'baru_1', email: 'user@example.test' });
    });

    it.each([
        ['name', ''],
        ['name', 'AB'],
        ['name', 'A'.repeat(101)],
        ['username', 'abcde'],
        ['username', 'a'.repeat(101)],
        ['username', '1pengguna'],
        ['username', 'user-name'],
        ['email', ''],
        ['email', 'invalid'],
        ['email', `${'a'.repeat(60)}@${'b'.repeat(35)}.test`],
    ])('rejects invalid %s: %s', (field, value) => {
        const result = AccountProfileSchema.validate({ ...profile, [field]: value });
        expect(result.success).toBe(false);
        expect(result.errors).toHaveProperty(field);
    });

    it('uses Indonesian field labels', () => {
        expect(AccountProfileSchema.validate({ ...profile, email: 'invalid' }).errors).toEqual({
            email: ['Email harus berupa email yang valid.'],
        });
    });

    it('accepts six character usernames consistently with login', () => {
        expect(AccountProfileSchema.validate({ ...profile, username: 'baru_1' }).success).toBe(true);
        expect(LoginSchema.validate({ username: 'baru_1', password: 'password' }).success).toBe(true);
        expect(LoginSchema.validate({ username: 'abcde', password: 'password' }).success).toBe(false);
    });
});

describe('account password', () => {
    it('accepts a different confirmed password', () => {
        expect(AccountPasswordSchema.validate(password).success).toBe(true);
    });

    it.each([
        ['current_password', ''],
        ['password', 'short'],
        ['password_confirmation', 'mismatch'],
    ])('rejects invalid %s', (field, value) => {
        const result = AccountPasswordSchema.validate({ ...password, [field]: value });
        expect(result.success).toBe(false);
        expect(result.errors).toHaveProperty(field);
    });

    it('rejects reusing the current password', () => {
        const result = AccountPasswordSchema.validate({
            ...password,
            password: 'password',
            password_confirmation: 'password',
        });
        expect(result.errors).toEqual({ password: ['Kata Sandi Baru harus berbeda dari kata sandi saat ini.'] });
    });
});

describe('account avatar', () => {
    it.each(['image/jpeg', 'image/png', 'image/webp'])(
        'accepts %s at the size limit and preserves the File',
        (type) => {
            const avatar = new File([new Uint8Array(2 * 1024 * 1024)], 'photo', { type });
            const result = AccountAvatarSchema.validate({ avatar });
            expect(result.success).toBe(true);
            expect(result.data?.avatar).toBe(avatar);
        },
    );

    it.each(['text/plain', 'image/svg+xml', 'image/gif'])('rejects %s', (type) => {
        const result = AccountAvatarSchema.validate({ avatar: new File(['file'], 'photo', { type }) });
        expect(result.errors).toEqual({ avatar: ['Foto Profil harus berformat JPG, JPEG, PNG, atau WebP.'] });
    });

    it('rejects files over 2 MB', () => {
        const result = AccountAvatarSchema.validate({
            avatar: new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'photo.png', { type: 'image/png' }),
        });
        expect(result.errors).toEqual({ avatar: ['Foto Profil maksimal 2 MB.'] });
    });

    it('requires a real File', () => {
        expect(AccountAvatarSchema.validate({ avatar: 'photo.png' }).errors).toEqual({
            avatar: ['Foto Profil harus berupa berkas.'],
        });
        expect(AccountAvatarSchema.validate({ avatar: null }).errors).toEqual({
            avatar: ['Foto Profil wajib dipilih.'],
        });
    });
});
