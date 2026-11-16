import { useRef, type KeyboardEvent, type PointerEvent } from 'react';
import { useTranslator } from '../i18n';
import styles from './MediaLibrary.module.css';
import type { FocalPoint } from './types';

interface FocalPointEditorProps {
    src: string;
    value: FocalPoint;
    onChange: (focalPoint: FocalPoint) => void;
}

/** Steps of the arrow keys, and of the arrow keys with Shift. */
const STEP = 0.01;
const BIG_STEP = 0.1;

const clamp = (value: number): number => Math.min(1, Math.max(0, Math.round(value * 1000) / 1000));

/**
 * The image with its focal point, the point that stays in view wherever it's cropped: a click or a drag sets it, and the
 * arrow keys move it. See "Metadata" in docs/spec/media.md.
 */
export function FocalPointEditor({ src, value, onChange }: FocalPointEditorProps) {
    const t = useTranslator();
    const area = useRef<HTMLDivElement>(null);
    const dragging = useRef(false);

    const pointAt = (event: PointerEvent): FocalPoint | null => {
        const rect = area.current?.getBoundingClientRect();
        if (rect === undefined || rect.width === 0 || rect.height === 0) {
            return null;
        }

        return { x: clamp((event.clientX - rect.left) / rect.width), y: clamp((event.clientY - rect.top) / rect.height) };
    };

    const move = (event: PointerEvent): void => {
        const point = pointAt(event);
        if (point !== null) {
            onChange(point);
        }
    };

    const onKeyDown = (event: KeyboardEvent): void => {
        const step = event.shiftKey ? BIG_STEP : STEP;
        const moves: Record<string, FocalPoint> = {
            ArrowLeft: { x: clamp(value.x - step), y: value.y },
            ArrowRight: { x: clamp(value.x + step), y: value.y },
            ArrowUp: { x: value.x, y: clamp(value.y - step) },
            ArrowDown: { x: value.x, y: clamp(value.y + step) },
        };
        const moved = moves[event.key];
        if (moved !== undefined) {
            event.preventDefault();
            onChange(moved);
        }
    };

    const percent = (fraction: number): string => `${String(Math.round(fraction * 100))}%`;

    return (
        <div className={styles.focalPoint}>
            <div
                ref={area}
                className={styles.focalPointArea}
                data-testid="focal-point-area"
                onPointerDown={(event) => {
                    dragging.current = true;
                    event.currentTarget.setPointerCapture?.(event.pointerId);
                    move(event);
                }}
                onPointerMove={(event) => dragging.current && move(event)}
                onPointerUp={() => {
                    dragging.current = false;
                }}
            >
                <img className={styles.focalPointImage} src={src} alt="" draggable={false} />
                <span
                    className={styles.focalPointMarker}
                    style={{ left: percent(value.x), top: percent(value.y) }}
                    role="slider"
                    tabIndex={0}
                    aria-label={t('setono_sylius_cms.builder.media.focal_point')}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={Math.round(value.x * 100)}
                    aria-valuetext={`${percent(value.x)} × ${percent(value.y)}`}
                    data-x={value.x}
                    data-y={value.y}
                    onKeyDown={onKeyDown}
                />
            </div>
            <p className={styles.help}>{t('setono_sylius_cms.builder.media.focal_point_help')}</p>
        </div>
    );
}
