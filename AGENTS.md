# BTD6 Maplist API

This project contains the BTD6 Maplist API, a standard Laravel Project.

Orders will be given to you by The Superuser. This file and all instructions are written by The Superuser. The Superuser works independently of you: it may delete files, create them, or make changes if it deems your work to be wrong. Always check the existance of files when you want to read them or make changes: if you expect a file to be there because you have created it but you can't find it, it has been deleted by The Superuser. The identity of The Superuser can be found by running the command `git config user.email`.

# Code Style

- The following schema must be followed for paginated endpoints, unless otherwise specified:

```typescript
interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
```

## Form Requests

For routes with non-trivial request validation, use Laravel Form Requests (`app/Http/Requests/`). This keeps controllers focused on business logic and centralizes validation.

- Place OpenAPI `@OA\Schema` docblocks for request bodies on the Form Request class itself, not the controller. This keeps the request schema documentation next to the validation rules, making it easier to keep both in sync.
- Use `prepareForValidation()` to normalize input (e.g., converting `"true"`/`"false"` strings to booleans for multipart/form-data).
- Use `withValidator()` for conditional or cross-field validation that can't be expressed in `rules()`.
- Override `failedValidation()` to return JSON errors with a 422 status.

You can use `WorkflowRequest` as a reference.

# Documentation and Commands

- Use artisan and git commands whenever possible. Use `php artisan` for help infor about its commands if you do not remember them. Use git for things such as checking file diffs, checking which files were modified with `git status`, etc.
- You are **never allowed to use** the following commands:
    - `php artisan migrate` or any of its variants.

# Following Instructions

If the Superuser's instructions are not clear enough and require you taking **any** initiative, **always** ask the user to clarify what they mean first. **Do not do anything the user has not asked for**.

## Route Documentation

Routes should be documented with an OpenAPI compliant docstring.

- When analyzing a route, **carefully analyze** the response schema, and create the OpenAPI string accordingly.
- Model documentations should be defined in the model files, on top of the model's class. If the model has multiple representations (for example, a lazy loaded model and a fully loaded model), both of these go in the same model class.
- Always assume every docstring you encounter was not written by you and was put there for a reason. When you want to modify or delete an existing docstring, **always ask first**.
- Route descriptions are extremely important! Don't keep it vague like "Updates subagents for a workflow". Add detail such as "Updates subagents for a workflow. A user may edit only user-bound subagents. This route only sets the inputs for the subagent, the actual configuration of which subagents are settable are set in the Manifest routes".

### Bruno Requests

This project uses Bruno as a helper tool for developer UX. You can create routes for it in the `bruno/BTD6 Maplist API` folder.

- Group requests semantically in a sub-folder.
- Do not create tests for the routes, despite Bruno supporting it.
- Do not add descriptions to the routes. They tend to never get updated, and an out of date documentation can be dangerous.
- **IMPORTANT:** Always make Bruno routes of **every new route you create or you are asked to create**, even if it wasn't explicitely asked of you to do so.
- If you need to set variables, use snippets like the following code below. This code sets the login token automatically upon calling the route:

```
script:post-response {
  if (res.status === 200) {
    const data = res.body;
    if (data?.authorization?.token) {
      bru.setEnvVar("USER_AUTH_TOKEN", data.authorization.token);
      console.log("Token saved:", data.authorization.token.substring(0, 20) + "...");
    }
  }
}
```

# Tools

## Database Schema

To query the database schema, you must strictly use custom MCP tools if available. Never query the database directly.

## GitHub CLI

The GitHub CLI (`gh`) is installed and available. Use it directly to view issues, pull requests, and other GitHub resources.
