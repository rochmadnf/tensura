export type User = {
    username: string;
    name: string;
    email: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
