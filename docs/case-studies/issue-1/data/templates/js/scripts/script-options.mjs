// lino-arguments maps declared options to environment variables and .lenv entries.
import { makeConfig } from 'lino-arguments';

const options = {
  'bump-type': {
    type: 'string',
    default: '',
    choices: ['major', 'minor', 'patch'],
  },
  description: { type: 'string', default: '' },
  'js-root': {
    type: 'string',
    default: '',
    describe: 'JavaScript package root (auto-detected by default)',
  },
  mode: {
    type: 'string',
    default: 'changeset',
    choices: ['changeset', 'instant'],
  },
  'should-pull': { type: 'boolean', default: false },
  'release-version': { type: 'string', default: '', env: 'VERSION' },
  'release-id': { type: 'string', default: '' },
  repository: { type: 'string', default: '' },
  'commit-sha': { type: 'string', default: '' },
  'tag-prefix': { type: 'string', default: 'v' },
};

export function scriptOptions(...names) {
  return makeConfig({
    env: { autoMap: true },
    yargs: ({ yargs }) =>
      yargs.options(
        Object.fromEntries(names.map((name) => [name, options[name]]))
      ),
  });
}
