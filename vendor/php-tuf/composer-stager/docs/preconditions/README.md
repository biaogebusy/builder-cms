# Precondition system

The precondition system defines and inspects the conditions necessary to perform core API operations, i.e., begin, stage, commit, and clean. In short, it provides mechanisms for capturing individual preconditions with verification logic, optionally collecting them into groups, and determining their status.

The class design contains three main concepts: 1) A precondition defines one atomic condition--e.g., directory exists or application is available--and the ability to test for its fulfillment. 2) A precondition tree is a composite of other preconditions and trees which can be mixed and nested indefinitely. In other words, it implements [the decorator design pattern](https://refactoring.guru/design-patterns/decorator). 3) An exception that can be thrown in case of an unfulfilled precondition and provide it to client code.

This diagram depicts the detailed interfaces and relationships. New concrete preconditions should inherit their corresponding abstract classes, which handle precondition-nonspecific features like recursion and exception-handling. 

<br>

<div align="center"><img src="detail.png" alt="Detail diagram" /></div>

<br>

Below is a depiction of the actual hierarchy of preconditions.

<br>

<div align="center"><img src="hierarchy.png" alt="Hierarchy diagram" /></div>

<br>

## Symlinks

The reasoning behind the preconditions related to symlinks and hard links is not necessarily obvious and bears special explanation:

- **No links of any kind on Windows.** ([`NoLinksExistOnWindows`](../../src/API/Service/Precondition/NoLinksExistOnWindowsInterface.php)) - PHP on Unix-like hosts will let you create a symlink pointing to a path that does not exist, but on Windows it will fail, creating a temporal coupling that cannot be supported. For example, given a symlink `link.txt` with a target `target.txt`, we would have to make sure that `target.txt` was always written first (which is infeasible) or the operation would fail, leaving the codebase in some state of corruption.
- **No absolute links** ([`NoAbsoluteSymlinksExist`](../../src/API/Service/Precondition/NoAbsoluteSymlinksExistInterface.php)) - An absolute link in the active directory will point to the same path on disk when copied to the staging directory, creating the possibility of staged operations changing production files.
- **No hard links** ([`NoHardLinksExist`](../../src/API/Service/Precondition/NoHardLinksExistInterface.php)) - Hard links point to inodes as opposed to mere paths, i.e., the actual data blocks on the disk. Therefore, a hard link _to_ a file essentially _is_ that file. So anything done to one is done to the other, with the same net effect as absolute links.
- **No soft links that point outside the codebase** ([`NoSymlinksPointOutsideTheCodebase`](../../src/API/Service/Precondition/NoSymlinksPointOutsideTheCodebaseInterface.php)) - Active and staging directories adjacent to one another on the filesystem have identical ancestors by definition. Hence, a relative link pointing out of one would target the same file as the corresponding link "next door" and, like previous scenarios, create the possibility of corrupting accidentally shared files. Links that point _within_ the codebase pose no threat--even if they cross internal package boundaries, i.e., Composer or other vendor libraries. The greatest risk in that case is probably links with missing targets, which seems improbable with low impact--it would represent an awfully bizarre bug on the part of the offending package and would pose no known danger to any Composer Stager operations.

All other links are permitted. Exclusions are respected, i.e., links at excluded paths will be ignored, since they won't be copied to the staging directory and thus pose no threat. 
