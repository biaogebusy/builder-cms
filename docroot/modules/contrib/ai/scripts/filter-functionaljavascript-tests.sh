#!/bin/bash
#
# Filter FunctionalJavascript test files based on branch context.
#
# - Tags: keep all FJS tests (full test run)
# - Main branches (e.g. 1.x, 2.x, 2.0.x, 1.3.x): remove ALL FJS tests
# - Issue branches: keep only FJS tests tagged with @group {issue_number}
#
# This works around a run-tests.sh limitation where --directory (used by the
# Drupal CI template for scoped discovery) ignores @group filtering via
# positional arguments. By removing test files before discovery, we achieve
# effective group filtering.
#
# Outputs "HAS_FJS=1" or "HAS_FJS=0" to indicate whether any FJS tests remain,
# which can be used to conditionally install ffmpeg.
#
# Expected environment variables (set by GitLab CI / Drupal CI template):
#   CI_COMMIT_TAG
#   CI_MERGE_REQUEST_SOURCE_BRANCH_NAME
#   CI_COMMIT_BRANCH
#   CI_PROJECT_DIR            - build directory (repo checkout)
#   CI_PROJECT_NAME           - project name (e.g. "ai")
#   _WEB_ROOT                 - Drupal web root (default: "web")
#   DRUPAL_PROJECT_FOLDER     - absolute path to the installed module (may not
#                               be set during before_script)

set -eo pipefail

# The test runner scans the *installed* module path (not the source checkout).
# Construct the path from CI variables if DRUPAL_PROJECT_FOLDER is not set.
if [ -n "${DRUPAL_PROJECT_FOLDER:-}" ] && [ -d "$DRUPAL_PROJECT_FOLDER" ]; then
  SEARCH_DIR="$DRUPAL_PROJECT_FOLDER"
else
  SEARCH_DIR="${CI_PROJECT_DIR:-.}/${_WEB_ROOT:-web}/modules/custom/${CI_PROJECT_NAME:-ai}"
fi

if [ ! -d "$SEARCH_DIR" ]; then
  echo "Warning: module directory not found at $SEARCH_DIR, falling back to CI_PROJECT_DIR."
  SEARCH_DIR="${CI_PROJECT_DIR:-.}"
fi

echo "Searching for FunctionalJavascript tests in: $SEARCH_DIR"

ISSUE_NUMBER=""
REMOVE_FJS="none"

if [ -n "${CI_COMMIT_TAG:-}" ]; then
  echo "Tag commit: keeping all FunctionalJavascript tests."
elif [ -n "${CI_MERGE_REQUEST_SOURCE_BRANCH_NAME:-}" ]; then
  echo "Checking merge request source branch: $CI_MERGE_REQUEST_SOURCE_BRANCH_NAME"
  if echo "$CI_MERGE_REQUEST_SOURCE_BRANCH_NAME" | grep -qE '(^|[-/])[0-9]+(\.[0-9]+)?\.x$'; then
    echo "Main branch: removing all FunctionalJavascript tests."
    REMOVE_FJS="all"
  else
    ISSUE_NUMBER=$(echo "$CI_MERGE_REQUEST_SOURCE_BRANCH_NAME" | grep -oE '[0-9]{4,}' | head -1 || true)
    echo "Issue branch: keeping only FunctionalJavascript tests with @group $ISSUE_NUMBER."
    [ -n "$ISSUE_NUMBER" ] && REMOVE_FJS="untagged"
    echo "Extracted issue number: $ISSUE_NUMBER"
  fi
elif [ -n "${CI_COMMIT_BRANCH:-}" ]; then
  echo "Checking commit branch: $CI_COMMIT_BRANCH"
  if echo "$CI_COMMIT_BRANCH" | grep -qE '(^|[-/])[0-9]+(\.[0-9]+)?\.x$'; then
    REMOVE_FJS="all"
  else
    ISSUE_NUMBER=$(echo "$CI_COMMIT_BRANCH" | grep -oE '[0-9]{4,}' | head -1 || true)
    [ -n "$ISSUE_NUMBER" ] && REMOVE_FJS="untagged"
  fi
fi

echo "REMOVE_FJS=$REMOVE_FJS, ISSUE_NUMBER=$ISSUE_NUMBER"
FJS_FILES=$(find -L "$SEARCH_DIR" -type f \( -path '*/FunctionalJavascript/*' -o -path '*/FunctionalJavascriptTests/*' \) -name '*Test.php' -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/bower_components/*' 2>/dev/null || true)

if [ "$REMOVE_FJS" = "all" ]; then
  echo "Main branch: removing all FunctionalJavascript tests."
  if [ -n "$FJS_FILES" ]; then
    echo "$FJS_FILES" | xargs -r rm -f
  fi
elif [ "$REMOVE_FJS" = "untagged" ] && [ -n "$ISSUE_NUMBER" ]; then
  echo "Issue branch: keeping only FunctionalJavascript tests with @group $ISSUE_NUMBER."
  if [ -n "$FJS_FILES" ]; then
    echo "$FJS_FILES" | while IFS= read -r file; do
      [ -z "$file" ] && continue
      if ! grep -Fq "@group $ISSUE_NUMBER" "$file"; then
        echo "  Removing: $file"
        rm -f "$file"
      fi
    done
  fi
fi

# Check if any FJS tests remain and output result.
if find -L "$SEARCH_DIR" -type f \( -path '*/FunctionalJavascript/*' -o -path '*/FunctionalJavascriptTests/*' \) -name '*Test.php' -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/bower_components/*' 2>/dev/null | grep -q .; then
  echo "HAS_FJS=1"
  export HAS_FJS=1
else
  echo "HAS_FJS=0"
  export HAS_FJS=0
fi
