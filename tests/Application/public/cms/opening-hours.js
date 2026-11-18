/**
 * The field control of the test application's opening hours field type (tests/Application/Field/OpeningHours.php), as a
 * project writes one: a custom element, loaded as an ES module from setono_sylius_cms.builder.scripts. The builder sets
 * .value, .field, .locale and .builder on it, and it dispatches a bubbling cms-change event with the new value in
 * detail.value. See "Field controls" in docs/spec/extending.md
 *
 * The value is {days: {monday: {opens: '09:00', closes: '17:00'}, …}, photo: '<media item UUID>'}, or null for none.
 */
const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

class OpeningHoursControl extends HTMLElement {
    #value = null;
    #field = null;
    #builder = null;
    #photoRow = document.createElement('p');

    get value() {
        return this.#value;
    }

    set value(value) {
        this.#value = value ?? null;
        this.#render();
    }

    get field() {
        return this.#field;
    }

    set field(field) {
        this.#field = field;
        this.#render();
    }

    // Opening hours are the same in every locale, so the locale isn't used
    locale = null;

    get builder() {
        return this.#builder;
    }

    set builder(builder) {
        this.#builder = builder;
        this.#render();
    }

    connectedCallback() {
        this.#render();
    }

    #render() {
        if (!this.isConnected || this.#field === null || this.#builder === null) {
            return;
        }

        const options = this.#field['x-cms-options'] ?? {};
        const strings = options.strings ?? {};
        const days = this.#value?.days ?? {};

        const table = document.createElement('table');
        for (const day of DAYS) {
            const row = table.insertRow();
            const name = document.createElement('th');
            name.scope = 'row';
            name.textContent = options.days?.[day] ?? day;
            row.append(name);

            for (const time of ['opens', 'closes']) {
                const input = document.createElement('input');
                input.type = 'time';
                input.name = `${day}.${time}`;
                input.value = days[day]?.[time] ?? '';
                input.addEventListener('change', () => this.#change());
                const label = document.createElement('label');
                label.style.display = 'flex';
                label.style.gap = '0.25rem';
                label.append(strings[time] ?? time, input);
                row.insertCell().append(label);
            }
        }

        this.replaceChildren(table, this.#photoRow);
        this.#renderPhoto();
    }

    /** The photo's buttons: pick or replace it from the media library, and remove it. */
    #renderPhoto() {
        const strings = this.#field['x-cms-options']?.strings ?? {};
        const photo = this.#value?.photo ?? null;
        const photoRow = this.#photoRow;
        const pick = document.createElement('button');
        pick.type = 'button';
        pick.textContent = this.#builder.trans(photo === null ? 'setono_sylius_cms.builder.media.pick' : 'setono_sylius_cms.builder.media.replace');
        pick.setAttribute('aria-label', `${strings.photo ?? 'Photo'}: ${pick.textContent}`);
        pick.addEventListener('click', async () => {
            const picked = await this.#builder.pickMedia({ kinds: ['image'], multiple: false });
            if (picked !== null && picked.length > 0) {
                this.#change(picked[0]);
            }
        });
        photoRow.replaceChildren(pick);

        if (photo !== null) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = this.#builder.trans('setono_sylius_cms.builder.media.remove');
            remove.setAttribute('aria-label', `${strings.photo ?? 'Photo'}: ${remove.textContent}`);
            remove.addEventListener('click', () => this.#change(null));
            photoRow.append(remove);
        }
    }

    /** Reads the hours from the inputs, with the photo, and tells the builder. A day with no times is closed. */
    #change(photo = this.#value?.photo ?? null) {
        const days = {};
        for (const day of DAYS) {
            const opens = this.querySelector(`input[name="${day}.opens"]`).value;
            const closes = this.querySelector(`input[name="${day}.closes"]`).value;
            if (opens !== '' || closes !== '') {
                days[day] = { opens, closes };
            }
        }

        const value = { ...(Object.keys(days).length > 0 ? { days } : {}), ...(photo === null ? {} : { photo }) };
        this.#value = Object.keys(value).length > 0 ? value : null;
        this.dispatchEvent(new CustomEvent('cms-change', { bubbles: true, detail: { value: this.#value } }));
        // The builder doesn't set back a value the control just gave, so it updates the photo's buttons itself, and
        // leaves the inputs, and the focus, as they are
        this.#renderPhoto();
    }
}

customElements.define('app-opening-hours', OpeningHoursControl);
