using System.Reflection;

namespace MyPackage;

/// <summary>
/// Provides package information.
/// </summary>
public static class PackageInfo
{
    /// <summary>
    /// Gets the package version from the assembly.
    /// </summary>
    public static string Version =>
        Assembly.GetExecutingAssembly()
            .GetCustomAttribute<AssemblyInformationalVersionAttribute>()
            ?.InformationalVersion?.Split('+')[0]
        ?? "0.0.0";
}
