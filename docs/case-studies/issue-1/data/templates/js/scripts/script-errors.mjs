export function failScript(context, error) {
  console.error(`${context}:`, error.message);
  if (process.env.DEBUG) {
    console.error('Stack trace:', error.stack);
  }
  process.exit(1);
}
