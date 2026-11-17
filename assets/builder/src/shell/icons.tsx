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

export function DesktopIcon() {
    return (
        <Icon>
            <path d="M2 3h12v8H2zM6 14h4M8 11v3" />
        </Icon>
    );
}

export function MobileIcon() {
    return (
        <Icon>
            <path d="M4.5 1.5h7v13h-7zM7 12.5h2" />
        </Icon>
    );
}

export function UndoIcon() {
    return (
        <Icon>
            <path d="M5.5 3 2.5 6l3 3M2.5 6h7a4 4 0 0 1 0 8H7" />
        </Icon>
    );
}

export function RedoIcon() {
    return (
        <Icon>
            <path d="M10.5 3l3 3-3 3M13.5 6h-7a4 4 0 0 0 0 8H9" />
        </Icon>
    );
}

export function ArrowUpIcon() {
    return (
        <Icon>
            <path d="M8 13V3M4 7l4-4 4 4" />
        </Icon>
    );
}

export function ChevronDownIcon() {
    return (
        <Icon>
            <path d="m4 6 4 4 4-4" />
        </Icon>
    );
}

export function ArrowDownIcon() {
    return (
        <Icon>
            <path d="M8 3v10M4 9l4 4 4-4" />
        </Icon>
    );
}

export function CopyIcon() {
    return (
        <Icon>
            <path d="M5.5 5.5h8v8h-8zM10.5 5.5v-3h-8v8h3" />
        </Icon>
    );
}

export function GripIcon() {
    return (
        <Icon>
            <path d="M6 3.5h.01M10 3.5h.01M6 8h.01M10 8h.01M6 12.5h.01M10 12.5h.01" strokeWidth="2.5" />
        </Icon>
    );
}

/** Marks something that has errors. */
export function EyeSlashIcon() {
    return (
        <Icon>
            <path d="M2 2l12 12M6.6 3.7A6.6 6.6 0 0 1 8 3.5c3.5 0 5.8 3 6.5 4.5-.3.7-1 1.8-2 2.8M10.9 11.7A6.4 6.4 0 0 1 8 12.5C4.5 12.5 2.2 9.5 1.5 8c.4-.9 1.3-2.2 2.6-3.2" />
            <path d="M6.6 6.6a2 2 0 0 0 2.8 2.8" />
        </Icon>
    );
}

export function AlertIcon() {
    return (
        <Icon>
            <circle cx="8" cy="8" r="6.25" />
            <path d="M8 4.75v3.75M8 11.25h.01" strokeWidth="1.75" />
        </Icon>
    );
}

/** Marks a link that leads nowhere in the preview: a broken chain. */
export function LinkOffIcon() {
    return (
        <Icon>
            <path d="M6.5 9.5 9.5 6.5M7 4.5l1-1a2.8 2.8 0 0 1 4 4l-1 1M9 11.5l-1 1a2.8 2.8 0 0 1-4-4l1-1M2 2l12 12" />
        </Icon>
    );
}
