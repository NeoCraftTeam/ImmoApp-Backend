---
inclusion: always
---
# Cursor Configuration



---
description: SkillKit skills integration - provides specialized capabilities and domain knowledge
globs: "**/*"
alwaysApply: true
---
# Skills System

You have access to specialized skills that can help complete tasks. Use the skillkit CLI to load skill instructions when needed.

## Available Skills

- **adapt**: Adapt designs to work across different screen sizes, devices, contexts, or platforms. Ensures consistent experience across varied environments.
- **animate**: Review a feature and enhance it with purposeful animations, micro-interactions, and motion effects that improve usability and delight.
- **arrange**: Improve layout, spacing, and visual rhythm. Fixes monotonous grids, inconsistent spacing, and weak visual hierarchy to create intentional compositions.
- **audit**: Perform comprehensive audit of interface quality across accessibility, performance, theming, and responsive design. Generates detailed report of issues with severity ratings and recommendations.
- **bolder**: Amplify safe or boring designs to make them more visually interesting and stimulating. Increases impact while maintaining usability.
- **bug-hunt**: No description available
- **clarify**: Improve unclear UX copy, error messages, microcopy, labels, and instructions. Makes interfaces easier to understand and use.
- **colorize**: Add strategic color to features that are too monochromatic or lack visual interest. Makes interfaces more engaging and expressive.
- **critique**: Evaluate design effectiveness from a UX perspective. Assesses visual hierarchy, information architecture, emotional resonance, and overall design quality with actionable feedback.
- **delight**: Add moments of joy, personality, and unexpected touches that make interfaces memorable and enjoyable to use. Elevates functional to delightful.
- **distill**: Strip designs to their essence by removing unnecessary complexity. Great design is simple, powerful, and clean.
- **enterprise**: No description available
- **extract**: Extract and consolidate reusable components, design tokens, and patterns into your design system. Identifies opportunities for systematic reuse and enriches your component library.
- **frontend-design**: Create distinctive, production-grade frontend interfaces with high design quality. Use this skill when the user asks to build web components, pages, artifacts, posters, or applications. Generates creative, polished code that avoids generic AI aesthetics.
- **harden**: Improve interface resilience through better error handling, i18n support, text overflow handling, and edge case management. Makes interfaces robust and production-ready.
- **normalize**: Normalize design to match your design system and ensure consistency
- **onboard**: Design or improve onboarding flows, empty states, and first-time user experiences. Helps users get started successfully and understand value quickly.
- **optimize**: Improve interface performance across loading speed, rendering, animations, images, and bundle size. Makes experiences faster and smoother.
- **overdrive**: Push interfaces past conventional limits with technically ambitious implementations. Whether that's a shader, a 60fps virtual table, spring physics on a dialog, or scroll-driven reveals — make users ask "how did they do that?"
- **owasp-security**: Use when reviewing code for security vulnerabilities, implementing authentication/authorization, handling user input, or discussing web application security. Covers OWASP Top 10:2025, ASVS 5.0, LLM Top 10 (2025), and Agentic AI security (2026).
- **polish**: Final quality pass before shipping. Fixes alignment, spacing, consistency, and detail issues that separate good from great.
- **quieter**: Tone down overly bold or visually aggressive designs. Reduces intensity while maintaining design quality and impact.
- **teach-impeccable**: One-time setup that gathers design context for your project and saves it to your AI config file. Run once to establish persistent design guidelines.
- **typeset**: Improve typography by fixing font choices, hierarchy, sizing, weight consistency, and readability. Makes text feel intentional and polished.

## How to Use Skills

When a task matches a skill's description, load it with:
```bash
skillkit read <skill-name>
```

The skill will provide detailed instructions for completing the task.

<!-- SKILLS_DATA_START -->
<skill>
<name>adapt</name>
<description>Adapt designs to work across different screen sizes, devices, contexts, or platforms. Ensures consistent experience across varied environments.</description>
<location>project</location>
</skill>

<skill>
<name>animate</name>
<description>Review a feature and enhance it with purposeful animations, micro-interactions, and motion effects that improve usability and delight.</description>
<location>project</location>
</skill>

<skill>
<name>arrange</name>
<description>Improve layout, spacing, and visual rhythm. Fixes monotonous grids, inconsistent spacing, and weak visual hierarchy to create intentional compositions.</description>
<location>project</location>
</skill>

<skill>
<name>audit</name>
<description>Perform comprehensive audit of interface quality across accessibility, performance, theming, and responsive design. Generates detailed report of issues with severity ratings and recommendations.</description>
<location>project</location>
</skill>

<skill>
<name>bolder</name>
<description>Amplify safe or boring designs to make them more visually interesting and stimulating. Increases impact while maintaining usability.</description>
<location>project</location>
</skill>

<skill>
<name>bug-hunt</name>
<description>No description available</description>
<location>project</location>
</skill>

<skill>
<name>clarify</name>
<description>Improve unclear UX copy, error messages, microcopy, labels, and instructions. Makes interfaces easier to understand and use.</description>
<location>project</location>
</skill>

<skill>
<name>colorize</name>
<description>Add strategic color to features that are too monochromatic or lack visual interest. Makes interfaces more engaging and expressive.</description>
<location>project</location>
</skill>

<skill>
<name>critique</name>
<description>Evaluate design effectiveness from a UX perspective. Assesses visual hierarchy, information architecture, emotional resonance, and overall design quality with actionable feedback.</description>
<location>project</location>
</skill>

<skill>
<name>delight</name>
<description>Add moments of joy, personality, and unexpected touches that make interfaces memorable and enjoyable to use. Elevates functional to delightful.</description>
<location>project</location>
</skill>

<skill>
<name>distill</name>
<description>Strip designs to their essence by removing unnecessary complexity. Great design is simple, powerful, and clean.</description>
<location>project</location>
</skill>

<skill>
<name>enterprise</name>
<description>No description available</description>
<location>project</location>
</skill>

<skill>
<name>extract</name>
<description>Extract and consolidate reusable components, design tokens, and patterns into your design system. Identifies opportunities for systematic reuse and enriches your component library.</description>
<location>project</location>
</skill>

<skill>
<name>frontend-design</name>
<description>Create distinctive, production-grade frontend interfaces with high design quality. Use this skill when the user asks to build web components, pages, artifacts, posters, or applications. Generates creative, polished code that avoids generic AI aesthetics.</description>
<location>project</location>
</skill>

<skill>
<name>harden</name>
<description>Improve interface resilience through better error handling, i18n support, text overflow handling, and edge case management. Makes interfaces robust and production-ready.</description>
<location>project</location>
</skill>

<skill>
<name>normalize</name>
<description>Normalize design to match your design system and ensure consistency</description>
<location>project</location>
</skill>

<skill>
<name>onboard</name>
<description>Design or improve onboarding flows, empty states, and first-time user experiences. Helps users get started successfully and understand value quickly.</description>
<location>project</location>
</skill>

<skill>
<name>optimize</name>
<description>Improve interface performance across loading speed, rendering, animations, images, and bundle size. Makes experiences faster and smoother.</description>
<location>project</location>
</skill>

<skill>
<name>overdrive</name>
<description>Push interfaces past conventional limits with technically ambitious implementations. Whether that&apos;s a shader, a 60fps virtual table, spring physics on a dialog, or scroll-driven reveals — make users ask &quot;how did they do that?&quot;</description>
<location>project</location>
</skill>

<skill>
<name>owasp-security</name>
<description>Use when reviewing code for security vulnerabilities, implementing authentication/authorization, handling user input, or discussing web application security. Covers OWASP Top 10:2025, ASVS 5.0, LLM Top 10 (2025), and Agentic AI security (2026).</description>
<location>project</location>
</skill>

<skill>
<name>polish</name>
<description>Final quality pass before shipping. Fixes alignment, spacing, consistency, and detail issues that separate good from great.</description>
<location>project</location>
</skill>

<skill>
<name>quieter</name>
<description>Tone down overly bold or visually aggressive designs. Reduces intensity while maintaining design quality and impact.</description>
<location>project</location>
</skill>

<skill>
<name>teach-impeccable</name>
<description>One-time setup that gathers design context for your project and saves it to your AI config file. Run once to establish persistent design guidelines.</description>
<location>project</location>
</skill>

<skill>
<name>typeset</name>
<description>Improve typography by fixing font choices, hierarchy, sizing, weight consistency, and readability. Makes text feel intentional and polished.</description>
<location>project</location>
</skill>
<!-- SKILLS_DATA_END -->
