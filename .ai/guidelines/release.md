## Release and pull request workflow

- When a release version changes, use Semantic Versioning (`MAJOR.MINOR.PATCH`) according to the existing release rules. Publish a GitHub release only when the version bump has an associated file under `resources/markdown/releases/MAJOR.MINOR.PATCH.md`.
- Before release or pull request work, verify that GitHub CLI is installed and authenticated with `gh --version` and `gh auth status`. Verify the repository identity and GitHub default branch with `gh repo view`.
- When release notes exist, include the version bump and release notes in the completion change, push the commit and matching `vMAJOR.MINOR.PATCH` tag, then create the GitHub release from that tag using the release note file.
- Compare the current branch with the GitHub repository's detected default branch. If they differ, do not create a pull request automatically. First present a proposed pull request title and body based on the actual changes and request explicit user approval.
- After approval, create the pull request from the current branch to the detected default branch. Do not assume the target branch is named `main`.
- Do not add Copilot, GitHub CLI, or other assistant attribution to commits, pull requests, release notes, tags, or releases.
