# Calendar Prototype Sandbox Notes

- **Mount expectations**: Each HTML file is scoped to the area currently occupied by the appointment calendar body. Global navigation, branding, and the layout-owned dark/light toggle remain untouched.
- **Container sizing**: Prototypes were authored against a max width of 6xl (approx 72rem). When mounted inside the production layout, apply `max-w-6xl mx-auto` or equivalent padding to mirror the sandbox spacing.
- **Sidebar width**: Internal provider rails assume a 220px column that sits inside the calendar canvas. This should not conflict with the existing application sidebar since it lives within the calendar surface, not the global frame.
- **Theme handling**: No JavaScript toggles are included. Apply the `dark` class / `data-theme="dark"` at the layout level to preview dark mode. All Tailwind `dark:` variants hook into that global state.
- **Stylesheet location**: Prototype utilities are compiled into `resources/css/calendar/tailwind-prototype.css`. Re-run `npx tailwindcss -i resources/css/calendar/prototype.css -o resources/css/calendar/tailwind-prototype.css --minify --content resources/views/calendar_prototype/*.html` after modifying markup.
- **Next steps**: Once approved, extract repeating fragments (header, provider list, chips) into Tailwind components before attaching any JavaScript.
