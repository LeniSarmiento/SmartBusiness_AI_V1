import { execFileSync } from "node:child_process";
import { mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

// Existing infrastructure and secret references are preserved. This script never creates resources.
function required(name) {
    if (!process.env[name]) throw new Error(`Missing ${name}`);
    return process.env[name];
}
const group = required("AZURE_RESOURCE_GROUP");
const appName = required("AZURE_CONTAINER_APP");
const jobName = required("AZURE_MIGRATION_JOB");
const apiImage = required("API_IMAGE");
const webImage = required("WEB_IMAGE");
function az(args) {
    return JSON.parse(
        execFileSync(
            "az",
            [...args, "--output", "json", "--only-show-errors"],
            { encoding: "utf8", maxBuffer: 8 * 1024 * 1024 },
        ),
    );
}
const app = az([
    "containerapp",
    "show",
    "--name",
    appName,
    "--resource-group",
    group,
]);
const template = app.properties.template;
for (const name of ["api", "web", "worker"]) {
    const container = template.containers.find((item) => item.name === name);
    if (!container)
        throw new Error(
            `Provision a container named ${name} before deploying.`,
        );
    container.image = name === "web" ? webImage : apiImage;
}
delete template.revisionSuffix;
const migration = az([
    "containerapp",
    "job",
    "show",
    "--name",
    jobName,
    "--resource-group",
    group,
]);
if (migration.properties.template.containers.length !== 1)
    throw new Error("Migration job must have exactly one container.");
const migrationContainer = migration.properties.template.containers[0];
if (
    JSON.stringify([
        ...(migrationContainer.command ?? []),
        ...(migrationContainer.args ?? []),
    ]) !== JSON.stringify(["php", "artisan", "migrate", "--force"])
) {
    throw new Error(
        "Migration job must run exactly php artisan migrate --force.",
    );
}
az([
    "containerapp",
    "job",
    "update",
    "--name",
    jobName,
    "--resource-group",
    group,
    "--image",
    apiImage,
    "--container-name",
    migrationContainer.name,
]);
const execution = az([
    "containerapp",
    "job",
    "start",
    "--name",
    jobName,
    "--resource-group",
    group,
]);
const executionName = execution.name;
if (!executionName)
    throw new Error("Migration execution did not return a name.");
let succeeded = false;
for (let attempt = 0; attempt < 60; attempt++) {
    const status = az([
        "containerapp",
        "job",
        "execution",
        "show",
        "--name",
        jobName,
        "--resource-group",
        group,
        "--job-execution-name",
        executionName,
    ]).properties.status;
    if (status === "Succeeded") {
        succeeded = true;
        break;
    }
    if (status === "Failed" || status === "Stopped")
        throw new Error(
            `Migration ${status}; application images were not updated.`,
        );
    await new Promise((resolve) => setTimeout(resolve, 10000));
}
if (!succeeded)
    throw new Error(
        "Migration timed out; application images were not updated.",
    );
const directory = mkdtempSync(join(tmpdir(), "smartbusiness-release-"));
const file = join(directory, "revision.json");
writeFileSync(
    file,
    JSON.stringify({
        name: appName,
        location: app.location,
        properties: { template },
    }),
);
az([
    "containerapp",
    "update",
    "--name",
    appName,
    "--resource-group",
    group,
    "--yaml",
    file,
]);
console.log(
    "Application revision updated. Check readiness, logs and a login smoke test before considering the deployment complete.",
);
