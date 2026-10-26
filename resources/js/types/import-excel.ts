export type ExcelImportSettings = {
    maxSizeBytes: number;
    maxSizeLabel: string;
    maxRows: number;
};

export type ExcelImportInstructionSection = {
    id: string;
    title: string;
    items: string[];
};
