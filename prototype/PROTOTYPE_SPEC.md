# 2DMIS Interactive Prototype Specification

## 1. Purpose

This prototype exists solely for stakeholder presentations and design validation.

It is NOT connected to any backend, database, or production code.

Its purpose is to demonstrate the future user experience, interface, and workflow of the 2DMIS while preserving the existing system architecture, terminology, navigation, and business processes.

The prototype should feel like a production-ready government management system while remaining completely independent from the actual application.

---

# 2. Scope

Improve the existing prototype.

Do NOT redesign it from scratch.

Before making changes:

- Inspect the existing prototype.
- Understand its structure.
- Preserve its design language.
- Reuse existing components whenever possible.
- Improve incrementally instead of rebuilding.

Every enhancement should feel like a refinement rather than a redesign.

---

# 3. Constraints

Work ONLY inside the prototype folder.

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

Additional HTML, CSS, JavaScript, images, or assets may be created ONLY inside the prototype folder.

---

# 4. Design Philosophy

The interface should communicate:

- professionalism
- trust
- simplicity
- clarity
- efficiency
- accessibility
- authority

The experience should resemble modern enterprise software used by government agencies.

Avoid:

- flashy gradients
- glassmorphism
- oversized shadows
- excessive animations
- gaming-inspired interfaces
- social media aesthetics
- unnecessary visual clutter

Prefer subtle refinement over dramatic redesigns.

---

# 5. Existing Design

Preserve:

- current branding
- navigation hierarchy
- sidebar layout
- workflow
- terminology
- information architecture
- overall system identity

Improve:

- spacing
- alignment
- typography
- responsiveness
- accessibility
- consistency
- usability
- visual hierarchy

---

# 6. Design Inspiration

Take inspiration from:

- USWDS 3.0
- GOV.UK Design System
- Singapore Government Design System (SGDS)
- IBM Carbon
- Microsoft Fluent 2

Do not copy any one design system directly.

Instead, combine their best practices into a cohesive and professional interface suitable for Philippine government use.

---

# 7. Government Color System

Use a restrained government color palette inspired by the Philippine national colors.

Primary Blue
#0038A8

Primary Hover
#002F87

Danger Red
#CE1126

Accent Gold
#F4C430

Sidebar
#1E293B

Workspace Background
#F8FAFC

Surface
#FFFFFF

Border
#E5E7EB

Primary Text
#111827

Secondary Text
#6B7280

Color Rules:

Blue should be used for:

- primary buttons
- active navigation
- active tabs
- selected table rows
- links
- focus states

Red should only be used for:

- delete
- destructive actions
- validation errors
- critical alerts

Gold should only be used sparingly for:

- branding
- important announcements
- notification indicators
- highlights

The interface should remain predominantly white and light gray.

---

# 8. Dashboard

Improve the dashboard by refining or adding:

- statistics cards
- recent activity
- quick actions
- announcements
- notifications
- charts (mock)
- calendar widget (mock)
- responsive layouts

Use realistic mock data only.

---

# 9. Records Module

The records table should be the primary focus.

Improve:

- search
- filters
- toolbar
- pagination
- row spacing
- typography
- status badges
- hover effects
- sorting indicators

Selected rows should use a subtle blue highlight.

Tables should remain highly readable.

---

# 10. Resident Details Panel

This is the primary interaction of the prototype.

Remove the concept of navigating to a separate View page.

Instead:

Clicking anywhere on a table row immediately opens a persistent slide-in details panel attached to the right side of the screen.

Never:

- navigate away
- replace the page
- open a centered modal

The table must remain visible while the panel is open.

Desktop:

420–500px width.

Tablet:

approximately half the screen.

Mobile:

full-width or bottom sheet.

The panel should include:

- resident photo/avatar
- resident ID
- status badge
- personal information
- household information
- contact information
- government IDs
- timeline
- notes
- attached documents (mock)
- audit information

Action buttons:

- Edit
- Print
- Generate Certificate
- Archive
- Delete

Buttons only simulate interaction.

---# 10. Resident Details Panel

This is the primary interaction of the prototype.

Viewing a resident record should never navigate to another page.

Clicking anywhere on a table row should immediately display the resident information while keeping the user on the Records page.

The presentation of the details panel must adapt to the current screen size.

Desktop (1024px and above)

- Display a persistent slide-in panel attached to the right side of the screen.
- Panel width should be approximately 420–500px.
- The records table must remain visible.
- The panel should not cover the entire workspace.

Tablet (768px–1023px)

- Display a narrower right-side panel occupying approximately 50–60% of the viewport.
- The records table should remain partially visible.
- The layout should prioritize readability.

Mobile (below 768px)

- Do NOT use the desktop right-side panel.
- Display the resident information as either:
  - a full-width slide-in drawer, or
  - a bottom sheet.
- The details view should occupy most or all of the screen.
- Touch interactions should be prioritized.
- Closing the details view should return the user directly to the records list.

Never simply shrink the desktop drawer to fit a smaller screen.

The panel should contain:

- resident photo/avatar
- resident ID
- status badge
- personal information
- household information
- contact information
- government IDs
- timeline
- notes
- attached documents (mock)
- audit information

Available actions:

- Edit
- Print
- Generate Certificate
- Archive
- Delete

Buttons only simulate interaction.

---

# 11. Navigation

Navigation should remain interactive.

You may create additional HTML pages inside the prototype folder if doing so improves the presentation.

Links should only navigate within the prototype.

No backend functionality is required.

---

# 12. Responsive Design

The prototype must use responsive layouts rather than simply scaling the desktop interface.

Every page should adapt its layout according to the available viewport.

Desktop (1024px and above)

- Full sidebar
- Multi-column layouts
- Dashboard cards displayed in multiple columns
- Persistent right-side details panel
- Full tables

Tablet (768px–1023px)

- Collapsible or compact sidebar
- Dashboard cards wrap naturally
- Tables may scroll horizontally if necessary
- Details panel occupies approximately half the screen

Mobile (below 768px)

- Single-column layout
- Navigation optimized for touch
- Collapsible sidebar or hamburger navigation
- Cards stacked vertically
- Forms displayed in a single column
- Large touch-friendly buttons
- Tables remain usable through responsive layouts or horizontal scrolling
- Details panel becomes a full-width drawer or bottom sheet

The application should never rely on browser zooming or viewport scaling to achieve responsiveness.

Layouts must adapt using responsive CSS techniques such as Flexbox, CSS Grid, media queries, relative sizing, and responsive spacing.

Avoid fixed widths whenever possible.

No page should introduce unnecessary horizontal scrolling.

Test responsiveness at the following viewport widths:

- 1440px
- 1280px
- 1024px
- 768px
- 576px
- 430px
- 390px
- 375px
- 320px

---

# 13. Accessibility

Follow accessibility best practices.

Include:

- semantic HTML
- keyboard navigation
- visible focus states
- sufficient color contrast
- responsive layouts
- accessible buttons
- accessible forms

---

# 14. Code Quality

Use:

- HTML5
- CSS3
- Vanilla JavaScript

Write modular, reusable, and well-commented code.

Reuse existing components whenever possible.

Avoid unnecessary dependencies.

---

# 15. Interaction Standards

Use subtle enterprise-style interactions.

Examples:

- sidebar hover
- button hover
- table row hover
- dropdown fade
- details panel slide
- tooltip fade

Animation duration should remain between 150–250ms.

Animations should improve usability rather than draw attention.

---

# 16. Completion Checklist

The prototype should satisfy the following:

✓ Navigation is interactive.

✓ Dashboard feels complete.

✓ Tables are polished.

✓ Search and filters are realistic.

✓ Clicking a table row opens the right-side details panel.

✓ Viewing a record never navigates away.

✓ Details panel is responsive.

✓ Typography is consistent.

✓ Color usage follows the government design system.

✓ Components share a consistent visual language.

✓ Accessibility is maintained.

✓ No files outside the prototype folder are modified.

---

# 17. Expected Deliverable

Produce a polished, presentation-ready, interactive prototype that demonstrates the future direction of the 2DMIS.

The prototype should feel modern, trustworthy, and enterprise-grade while preserving the existing architecture, workflow, navigation, terminology, and branding.

Always improve the existing prototype instead of rebuilding it.