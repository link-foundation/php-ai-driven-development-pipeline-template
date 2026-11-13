import { readFileSync } from 'node:fs';

export function readWorkflow(filePath) {
  return readFileSync(filePath, 'utf8').replaceAll('\r\n', '\n');
}

export function getJobBlock(workflow, jobName) {
  const lines = workflow.replaceAll('\r\n', '\n').split('\n');
  const start = lines.indexOf(`  ${jobName}:`);
  if (start === -1) {
    return '';
  }
  const end = lines.findIndex(
    (line, index) => index > start && /^ {2}[a-zA-Z0-9_-]+:\s*$/.test(line)
  );
  return lines.slice(start, end === -1 ? lines.length : end).join('\n');
}
