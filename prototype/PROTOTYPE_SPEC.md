# 2DMIS Interactive Prototype Specification

## 1. Purpose

This prototype is for stakeholder presentations only.

It is NOT connected to any backend or database.

Its purpose is to demonstrate the proposed user experience, interface, and navigation of the future 2DMIS while preserving the existing architecture, workflow, and terminology.

---

# 2. Scope

Improve the existing prototype.

Do NOT redesign it from scratch.

Before making changes:

- Inspect the current prototype.
- Understand its structure.
- Reuse existing code whenever possible.
- Improve incrementally.

---

# 3. Constraints

## Work only inside the prototype folder.

Never modify:

- backend
- routes
- controllers
- models
- migrations
- production HTML
- production CSS
- production JavaScript
- database
- configuration files

Additional HTML, CSS, JS, and assets may be created only inside the prototype folder.

---

# 4. Existing Design

Preserve:

- current color palette
- branding
- sidebar layout
- navigation hierarchy
- terminology
- workflow
- overall architecture

Do not replace the visual identity.

Instead improve:

- spacing
- alignment
- typography
- consistency
- usability
- responsiveness
- accessibility

---

# 5. Design Inspiration

Take inspiration from:

- USWDS 3.0
- GOV.UK Design System
- Singapore Government Design System

The interface should feel like a modern government administration platform.

---

# 6. Dashboard Improvements

Improve the dashboard by adding or refining:

- statistics cards
- recent activity
- announcements
- quick actions
- notifications
- charts (mock)
- calendar widget (mock)
- responsive layouts

Use mock data only.

---

# 7. Records Module

Improve:

- search
- filters
- table layout
- pagination
- row hover
- status badges
- toolbar
- sorting indicators

The table should remain the primary focus.

---

# 8. Resident Details Panel

This is the most important interaction.

Remove the concept of navigating to a separate View page.

Instead:

Clicking anywhere on a table row immediately opens a persistent slide-in panel attached to the right side of the screen.

Never:

- navigate away
- replace the page
- open a centered modal

The table must remain visible.

---

## Panel Contents

Include:

- resident photo/avatar
- resident ID
- QR code placeholder (optional)
- status badge
- personal information
- household information
- contact information
- government IDs
- notes
- timeline
- attached documents (mock)
- audit information

Buttons:

- Edit
- Print
- Generate Certificate
- Archive
- Delete

Buttons only simulate interaction.

---

# 9. Navigation

Navigation should remain interactive.

You may create additional HTML pages inside the prototype folder if they improve the presentation.

Links should work only within the prototype.

No backend logic.

---

# 10. Responsive Design

Desktop

- right panel approximately 420–500px
- table remains visible

Tablet

- panel occupies around half the screen

Mobile

- panel becomes full-width or slides from the bottom/right

Sidebar should collapse on smaller screens.

---

# 11. Accessibility

Follow good accessibility practices:

- semantic HTML
- keyboard navigation
- visible focus states
- proper color contrast
- responsive layouts

---

# 12. Code Quality

Use:

- HTML5
- CSS3
- Vanilla JavaScript

Write modular, reusable, and well-commented code.

Reuse existing components whenever possible.

Avoid unnecessary dependencies.

---

# 13. Expected Deliverable

Produce a polished, interactive, presentation-ready prototype.

The prototype should feel like a production-quality government information system while remaining completely independent of the actual application.

Always improve the existing prototype instead of rebuilding it.