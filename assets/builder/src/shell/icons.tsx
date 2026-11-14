import type { SVGProps } from 'react';

function Icon({ children, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            width="16"
            height="16"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
            {...props}
        >
            {children}
        </svg>
    );
}

export function ArrowLeftIcon() {
    return (
        <Icon>
            <path d="M13 8H3M7 4 3 8l4 4" />
        </Icon>
    );
}

export function PlusIcon() {
    return (
        <Icon>
            <path d="M8 3v10M3 8h10" />
        </Icon>
    );
}

export function TrashIcon() {
    return (
        <Icon>
            <path d="M2.5 4h11M6.5 4V2.5h3V4M4 4l.7 9.5h6.6L12 4M6.75 6.5v4.5M9.25 6.5v4.5" />
        </Icon>
    );
}
