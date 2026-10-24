export class ListingSearch {
    applied: string;
    pending: { keyword: string; token: number } | null = null;
    private sequence = 0;

    constructor(keyword: string) {
        this.applied = keyword.trim();
    }

    begin(keyword: string): number | null {
        if (this.pending?.keyword === keyword || (!this.pending && this.applied === keyword)) return null;
        const token = ++this.sequence;
        this.pending = { keyword, token };
        return token;
    }

    succeed(token: number, keyword: string) {
        if (this.pending?.token === token) this.applied = keyword;
    }

    finish(token: number) {
        if (this.pending?.token === token) this.pending = null;
    }

    navigate(keyword: string) {
        this.applied = keyword.trim();
        this.pending = null;
    }
}
