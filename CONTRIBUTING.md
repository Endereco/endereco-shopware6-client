# Contributor Guidelines

## General Requirements

- **Repository Structure**: This project maintains separate main branches for different Shopware 6 versions:
    - `main-6.6` - Latest Shopware 6.6.x
    - `main-6.5` - Shopware 6.5.x
    - `main-6.4` - Shopware 6.4.x (PHP 7 compatible)

- **Contribution Process**: All changes must be submitted via pull request after forking the repository

- **Branch Strategy**: Create feature branches from the appropriate main branch based on your target Shopware version

## Code Quality Standards

Every commit must meet these requirements:

1. **Stable State**: Each commit should leave the codebase in a working, stable state
2. **Quality Assurance**: Run `composer run qa` - it must pass without errors
3. **Installation Tests**: The plugin must be installable and uninstallable with every commit
4. **Cross-Version Compatibility**: If your changes apply to multiple Shopware versions, ensure commits can be cherry-picked to other branches without conflicts
5. **Don't break**: The existing functionality should not break 

## Commit Message Guidelines

Follow our commit message format:

### Structure
```
Title: Brief description in imperative mood (under 70 characters)

Body:
Explain WHY the changes are needed (4-5 sentences max).
Reference relevant issues, meetings, or discussions.
Keep lines under 70 characters for readability.
You are writing this text for a reviewer. Don't make his life hard.

For implementation details see [ISSUE-NUMBER].
```

### Requirements

**Title:**
- Use English and imperative language ("Add feature" not "Added feature")
- Answer "WHAT?" - describe what the commit does
- Keep under 70 characters
- No issue numbers in the title

**Body:**
- Explain "WHY?" - provide context for the changes
- Reference issues, emails, or meetings with specific identifiers
- Use professional, neutral language
- Break lines at ~70 characters
- Include 4-5 sentences maximum

**Example Good Commit:**

```
Add keyboard navigation support for autocomplete dropdowns

Enable users to navigate autocomplete suggestions using keyboard inputs
to improve accessibility compliance and user experience. Users can now
use Tab, Enter, and arrow keys to interact with address suggestions
without requiring mouse input.

This change addresses accessibility requirements outlined in WCAG 2.1
guidelines and moves the solution towards better EAA compatibility.

For implementation details see DEV-456. Related accessibility audit
findings documented in DEV-789.
```

### What to Avoid

- Vague titles like "fixed stuff" or "updates"
- Multiple unrelated changes in one commit
- Missing context about why changes were made
- Unprofessional language or jokes
- Lines exceeding 70 characters
- Mixing different types of changes (bug fixes + new features + refactoring)
- Adding fixes for previous commits. Just amend them yourself. Please.
- Too much text
- Technical details of the implementation, unless they are not understandable from reading the code

## Version-Specific Considerations

**For main-6.4 branch:**
- Ensure PHP 7 compatibility
- Test with minimum PHP version requirements
- Avoid PHP 8+ specific features

**For cross-version changes:**
- Test cherry-picking to all relevant branches
- Resolve any conflicts before submitting PR
- Document which branches the change applies to

## Pull Request Requirements

Before submitting your PR:

1. ✅ All commits follow the message guidelines above
2. ✅ `composer run qa` passes without errors
3. ✅ Plugin installs and uninstalls successfully
4. ✅ Feature branch created from appropriate main branch
5. ✅ Cross-version compatibility tested (if applicable)
6. ✅ PHP version compatibility verified (especially for main-6.4)

## Quality Checklist

Use this checklist for each commit:

- [ ] Commit has clear, imperative title under 70 characters
- [ ] Body explains business reason/context for changes
- [ ] Professional language used throughout
- [ ] Lines broken at ~70 characters for readability
- [ ] References to relevant issues/meetings included
- [ ] Code passes `composer run qa`
- [ ] Plugin installation/uninstallation works
- [ ] Changes are logically grouped (not mixing unrelated modifications)
- [ ] There are no fixes for previous commits in new commits
- [ ] Cross-branch compatibility considered and tested

## Getting Help

If you're unsure about any of these requirements or need clarification on the commit message format, please ask in the issue comments before starting work. We're happy to provide guidance to ensure your contribution meets our standards.

---

*Note: These guidelines ensure code quality, maintainability, and a clear project history. Following them helps reviewers understand your changes and makes the codebase easier to maintain long-term.*

---