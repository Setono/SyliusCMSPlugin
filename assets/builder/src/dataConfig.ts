/**
 * The configuration the page gives an app it mounts, such as the builder or the media library: JSON in the element's
 * data-config attribute, which Twig prints.
 *
 * @param missing what to throw when the element has none
 */
export function readDataConfig<T>(element: HTMLElement, missing: string): T {
    const json = element.dataset.config;
    if (json === undefined || json === '') {
        throw new Error(missing);
    }

    return JSON.parse(json) as T;
}
