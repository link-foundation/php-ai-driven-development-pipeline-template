namespace MyPackage;

/// <summary>
/// Provides basic arithmetic operations.
/// </summary>
/// <remarks>
/// Replace this with your actual implementation.
/// </remarks>
public static class Calculator
{
    /// <summary>
    /// Adds two numbers together.
    /// </summary>
    /// <param name="a">First number.</param>
    /// <param name="b">Second number.</param>
    /// <returns>Sum of <paramref name="a"/> and <paramref name="b"/>.</returns>
    /// <example>
    /// <code>
    /// var result = Calculator.Add(2, 3);
    /// Console.WriteLine(result); // Output: 5
    /// </code>
    /// </example>
    public static long Add(long a, long b) => a + b;

    /// <summary>
    /// Multiplies two numbers together.
    /// </summary>
    /// <param name="a">First number.</param>
    /// <param name="b">Second number.</param>
    /// <returns>Product of <paramref name="a"/> and <paramref name="b"/>.</returns>
    /// <example>
    /// <code>
    /// var result = Calculator.Multiply(2, 3);
    /// Console.WriteLine(result); // Output: 6
    /// </code>
    /// </example>
    public static long Multiply(long a, long b) => a * b;

    /// <summary>
    /// Delays execution for the specified duration.
    /// </summary>
    /// <param name="seconds">Duration to wait in seconds.</param>
    /// <param name="cancellationToken">Optional cancellation token.</param>
    /// <returns>A task that completes after the specified delay.</returns>
    /// <example>
    /// <code>
    /// await Calculator.DelayAsync(1.0);
    /// </code>
    /// </example>
    public static async Task DelayAsync(double seconds, CancellationToken cancellationToken = default)
    {
        var duration = TimeSpan.FromSeconds(seconds);
        await Task.Delay(duration, cancellationToken).ConfigureAwait(false);
    }
}
