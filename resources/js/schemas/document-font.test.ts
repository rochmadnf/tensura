import { describe, expect, it } from 'vite-plus/test';
import { createDocumentFontSchema } from './document-font';

describe('font upload validation', () => {
    const schema = createDocumentFontSchema(10);
    it('requires an actual TTF or OTF file within the upload size', () => {
        expect(schema.validate({ font: null }).success).toBe(false);
        expect(schema.validate({ font: new File(['font'], 'font.TTF') }).success).toBe(true);
        expect(schema.validate({ font: new File(['font'], 'font.otf') }).success).toBe(true);
        expect(schema.validate({ font: new File(['font'], 'font.php') }).success).toBe(false);
        expect(schema.validate({ font: new File([new Uint8Array(10241)], 'font.ttf') }).success).toBe(false);
    });
});
