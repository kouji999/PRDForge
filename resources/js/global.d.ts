type _RouteParams = Record<string, string | number>;

declare global {
    function route(name?: string, params?: _RouteParams): string & { current(): string };
}

export {};
