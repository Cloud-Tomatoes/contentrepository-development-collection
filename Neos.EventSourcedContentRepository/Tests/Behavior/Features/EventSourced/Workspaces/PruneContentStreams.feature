@fixtures
Feature: If content streams are not in use anymore by the workspace, they can be properly pruned - this is
  tested here.

  Background:
    Given I have no content dimensions
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': {}
    """
    And the command CreateRootWorkspace is executed with payload:
      | Key                     | Value           |
      | workspaceName           | "live"          |
      | contentStreamIdentifier | "cs-identifier" |
    And the graph projection is fully up to date
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key                      | Value                                  |
      | contentStreamIdentifier  | "cs-identifier"                        |
      | nodeAggregateIdentifier  | "root-node"                            |
      | nodeTypeName             | "Neos.ContentRepository:Root"          |
      | initiatingUserIdentifier | "00000000-0000-0000-0000-000000000000" |
    And the graph projection is fully up to date

  Scenario: content streams are marked as IN_USE_BY_WORKSPACE properly after creation
    Then the content stream "cs-identifier" has state "IN_USE_BY_WORKSPACE"
    Then the content stream "non-existing" has state ""

  Scenario: on creating a nested workspace, the new content stream is marked as IN_USE_BY_WORKSPACE.
    When the command CreateWorkspace is executed with payload:
      | Key                     | Value                |
      | workspaceName           | "user-test"          |
      | baseWorkspaceName       | "live"               |
      | contentStreamIdentifier | "user-cs-identifier" |
    And the graph projection is fully up to date

    Then the content stream "user-cs-identifier" has state "IN_USE_BY_WORKSPACE"

  Scenario: when rebasing a nested workspace, the new content stream will be marked as IN_USE_BY_WORKSPACE; and the old content stream is NO_LONGER_IN_USE.
    When the command CreateWorkspace is executed with payload:
      | Key                     | Value                |
      | workspaceName           | "user-test"          |
      | baseWorkspaceName       | "live"               |
      | contentStreamIdentifier | "user-cs-identifier" |
    And the graph projection is fully up to date
    When the command "RebaseWorkspace" is executed with payload:
      | Key           | Value       |
      | workspaceName | "user-test" |
    And the graph projection is fully up to date

    When I am in the active content stream of workspace "user-test" and Dimension Space Point {}
    Then the current content stream has state "IN_USE_BY_WORKSPACE"
    And the content stream "user-cs-identifier" has state "NO_LONGER_IN_USE"


  Scenario: when pruning content streams, NO_LONGER_IN_USE content streams will be properly cleaned from the graph projection.
    When the command CreateWorkspace is executed with payload:
      | Key                     | Value                |
      | workspaceName           | "user-test"          |
      | baseWorkspaceName       | "live"               |
      | contentStreamIdentifier | "user-cs-identifier" |
    And the graph projection is fully up to date
    When the command "RebaseWorkspace" is executed with payload:
      | Key           | Value       |
      | workspaceName | "user-test" |
    And the graph projection is fully up to date
    # now, we have one unused content stream (the old content stream of the user-test workspace)

    When I prune unused content streams
    And the graph projection is fully up to date

    When I am in content stream "user-cs-identifier" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "root-node" not to exist in the subgraph

    When I am in the active content stream of workspace "user-test" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "root-node" to exist in the subgraph

  Scenario: NO_LONGER_IN_USE content streams can be cleaned up completely (simple case)

    When the command CreateWorkspace is executed with payload:
      | Key                     | Value                |
      | workspaceName           | "user-test"          |
      | baseWorkspaceName       | "live"               |
      | contentStreamIdentifier | "user-cs-identifier" |
    And the graph projection is fully up to date
    When the command "RebaseWorkspace" is executed with payload:
      | Key           | Value       |
      | workspaceName | "user-test" |
    And the graph projection is fully up to date
    # now, we have one unused content stream (the old content stream of the user-test workspace)

    When I prune unused content streams
    And the graph projection is fully up to date
    And I prune removed content streams from the event stream

    Then I expect exactly 0 events to be published on stream "Neos.ContentRepository:ContentStream:user-cs-identifier"


  Scenario: NO_LONGER_IN_USE content streams are only cleaned up if no other content stream which is still in use depends on it
    # we build a "review" workspace, and then a "user-test" workspace depending on the review workspace.
    When the command CreateWorkspace is executed with payload:
      | Key                     | Value                  |
      | workspaceName           | "review"               |
      | baseWorkspaceName       | "live"                 |
      | contentStreamIdentifier | "review-cs-identifier" |
    And the graph projection is fully up to date
    And the command CreateWorkspace is executed with payload:
      | Key                     | Value                |
      | workspaceName           | "user-test"          |
      | baseWorkspaceName       | "review"             |
      | contentStreamIdentifier | "user-cs-identifier" |
    And the graph projection is fully up to date

    # now, we rebase the "review" workspace, effectively marking the "review-cs-identifier" content stream as NO_LONGER_IN_USE.
    # however, we are not allowed to drop the content stream from the event store yet, because the "user-cs-identifier" is based
    # on the (no-longer-in-direct-use) review-cs-identifier.
    When the command "RebaseWorkspace" is executed with payload:
      | Key           | Value       |
      | workspaceName | "review" |
    And the graph projection is fully up to date

    When I prune unused content streams
    And the graph projection is fully up to date
    And I prune removed content streams from the event stream

    # the events should still exist
    Then I expect exactly 2 events to be published on stream "Neos.ContentRepository:ContentStream:review-cs-identifier"