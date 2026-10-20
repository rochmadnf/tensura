export type User = {
    id: string;
    username: string;
    name: string;
    email: string;
    avatar: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
